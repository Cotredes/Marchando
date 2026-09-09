<?php

namespace App;

use App\Models\ActiveTableOrder;
use App\Models\DiningTable;
use App\Models\Employee;
use App\Models\Order;
use App\Models\OrderDiscount;
use App\Models\OrderEvent;
use App\Models\OrderLine;
use App\Models\OrderRound;
use App\Models\OrderSplitPlan;
use App\Models\Restaurant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;

class PosService
{
    public function verifyOperator(Restaurant $restaurant, int $employeeId, string $pin): Employee
    {
        $employee = $restaurant->employees()->with('operationalRoles')->whereKey($employeeId)->where('is_active', true)->first();
        if (! $employee || ! $employee->hasPin() || ! Hash::check($pin, $employee->pin_hash) || ! $employee->operationalRoles->contains(fn ($role) => in_array($role->code, ['service', 'manager'], true))) {
            throw new InvalidArgumentException('No se pudo verificar el empleado operativo.');
        }

        return $employee;
    }

    public function open(Restaurant $restaurant, DiningTable $table, Employee $employee, ?int $guestCount, User $user): Order
    {
        return DB::transaction(function () use ($restaurant, $table, $employee, $guestCount, $user): Order {
            $table = DiningTable::query()->with('zone')->lockForUpdate()->findOrFail($table->id);
            abort_unless($table->restaurant_id === $restaurant->id && $table->zone?->restaurant_id === $restaurant->id, 404);
            if (! $table->is_active || ! $table->zone?->is_active || ! $restaurant->dine_in_enabled) {
                throw new InvalidArgumentException('Esta mesa no está disponible para abrir una cuenta.');
            }
            $existing = ActiveTableOrder::query()->where('restaurant_id', $restaurant->id)->where('dining_table_id', $table->id)->first();
            if ($existing) {
                $existingOrder = $existing->order()->with('table.zone')->firstOrFail();
                if ($existingOrder->current_employee_id !== $employee->id) {
                    $previousEmployeeId = $existingOrder->current_employee_id;
                    $existingOrder->update(['current_employee_id' => $employee->id, 'version' => $existingOrder->version + 1]);
                    $this->event($existingOrder, 'employee_changed', $user, $employee, ['previous_employee_id' => $previousEmployeeId]);
                }

                return $existingOrder->fresh(['table.zone', 'currentEmployee']);
            }
            abort_unless($employee->restaurant_id === $restaurant->id && $employee->is_active, 422);
            $now = CarbonImmutable::now();
            $order = $restaurant->orders()->create([
                'dining_table_id' => $table->id, 'opened_by_user_id' => $user->id, 'opened_by_employee_id' => $employee->id,
                'current_employee_id' => $employee->id, 'channel' => 'dine_in', 'status' => 'open', 'currency' => $restaurant->currency,
                'guest_count' => $guestCount, 'business_date' => $now->setTimezone($restaurant->timezone)->toDateString(), 'opened_at' => $now,
            ]);
            ActiveTableOrder::create(['restaurant_id' => $restaurant->id, 'dining_table_id' => $table->id, 'order_id' => $order->id]);
            $order->rounds()->create(['restaurant_id' => $restaurant->id, 'sequence' => 1, 'draft_slot' => 1, 'status' => 'draft', 'created_by_user_id' => $user->id, 'created_by_employee_id' => $employee->id]);
            $this->event($order, 'opened', $user, $employee, ['guest_count' => $guestCount]);

            return $order->load('table.zone');
        });
    }

    public function switchEmployee(Order $order, Employee $employee, User $user): Order
    {
        return DB::transaction(function () use ($order, $employee, $user): Order {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);
            abort_unless($order->status === 'open' && $order->restaurant_id === $employee->restaurant_id && $employee->is_active, 422);
            $old = $order->current_employee_id;
            $order->update(['current_employee_id' => $employee->id, 'version' => $order->version + 1]);
            $this->event($order, 'employee_changed', $user, $employee, ['previous_employee_id' => $old]);

            return $order->fresh(['table.zone', 'currentEmployee']);
        });
    }

    public function draft(Order $order, User $user): OrderRound
    {
        return DB::transaction(function () use ($order, $user): OrderRound {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);
            abort_unless($order->status === 'open', 422);
            $draft = $order->rounds()->where('draft_slot', 1)->first();
            if ($draft) {
                return $draft->load('lines.modifiers', 'createdByEmployee');
            }
            $sequence = ((int) $order->rounds()->max('sequence')) + 1;

            return $order->rounds()->create(['restaurant_id' => $order->restaurant_id, 'sequence' => $sequence, 'draft_slot' => 1, 'status' => 'draft', 'created_by_user_id' => $user->id, 'created_by_employee_id' => $order->current_employee_id])->load('lines.modifiers', 'createdByEmployee');
        });
    }

    public function addLine(Order $order, OrderRound $round, array $data, User $user): OrderLine
    {
        return DB::transaction(function () use ($order, $round, $data, $user): OrderLine {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);
            $round = OrderRound::query()->lockForUpdate()->findOrFail($round->id);
            abort_unless($order->restaurant_id === $round->restaurant_id && $round->order_id === $order->id && $round->isDraft() && $order->status === 'open', 404);
            $this->assertNoPayments($order);
            $product = $order->restaurant->products()->with(['restaurant', 'category', 'formats', 'allergens', 'modifierGroupAssignments.group.options'])->findOrFail((int) $data['product_id']);
            if (! $product->isEffectivelyAvailable('dine_in')) {
                throw new InvalidArgumentException('Este producto ya no está disponible.');
            }
            if ($product->effectiveVat() === null) {
                throw new InvalidArgumentException('Este producto no tiene un IVA configurado.');
            }
            $formats = $product->formats;
            $format = null;
            if ($formats->isNotEmpty()) {
                if (empty($data['format_id'])) {
                    throw new InvalidArgumentException('Debes elegir un formato.');
                }
                $format = $formats->firstWhere('id', (int) $data['format_id']);
                if (! $format || ! $format->is_active) {
                    throw new InvalidArgumentException('El formato seleccionado ya no está disponible.');
                }
            } elseif (! empty($data['format_id'])) {
                throw new InvalidArgumentException('Este producto no tiene formatos.');
            }
            $selections = $this->normalizeSelections($data['selections'] ?? []);
            $this->validateSelections($product, $selections);
            $price = app(CatalogPriceCalculator::class)->calculate($product, $format, $selections);
            $quantity = max(1, (int) ($data['quantity'] ?? 1));
            if ($quantity > 99) {
                throw new InvalidArgumentException('La cantidad máxima es 99.');
            }
            $notes = filled($data['notes'] ?? null) ? trim((string) $data['notes']) : null;
            $key = sha1(json_encode([$product->id, $format?->id, $selections, $notes, $order->current_employee_id], JSON_THROW_ON_ERROR));
            $line = $round->lines()->with('modifiers')->get()->first(fn ($item) => ($item->snapshot['configuration_key'] ?? null) === $key);
            if ($product->track_stock && $product->stock_quantity < (($line?->quantity ?? 0) + $quantity)) {
                throw new InvalidArgumentException($product->name.' está agotado o sin stock suficiente.');
            }
            if ($line) {
                $line->update(['quantity' => $line->quantity + $quantity, 'line_total_minor' => ($line->quantity + $quantity) * $line->unit_total_minor, 'active_line_total_minor' => ($line->quantity + $quantity) * ($line->manual_unit_total_minor ?? $line->unit_total_minor)]);
            } else {
                $position = ((int) $round->lines()->max('position')) + 10;
                $snapshot = $this->snapshot($order, $product, $format, $price, $quantity, $notes, $key, $selections);
                $line = $round->lines()->create(['restaurant_id' => $order->restaurant_id, 'order_id' => $order->id, 'product_id' => $product->id, 'product_format_id' => $format?->id, 'employee_id' => $order->current_employee_id, 'user_id' => $user->id, 'product_name' => $product->name, 'format_name' => $price['format_name'], 'quantity' => $quantity, 'voided_quantity' => 0, 'unit_base_minor' => $price['base_minor'], 'unit_modifiers_minor' => $price['total_minor'] - $price['base_minor'], 'unit_total_minor' => $price['total_minor'], 'cost_minor' => $product->cost_minor, 'line_total_minor' => $quantity * $price['total_minor'], 'active_line_total_minor' => $quantity * $price['total_minor'], 'vat_rate' => $price['vat_rate'], 'currency' => $price['currency'], 'notes' => $notes, 'snapshot' => $snapshot, 'position' => $position]);
                foreach ($snapshot['modifiers'] as $modifier) {
                    $line->modifiers()->create(['restaurant_id' => $order->restaurant_id, ...$modifier]);
                }
            }
            $round->increment('version');
            $this->refreshTotal($order, $user, 'line_added', $line->employee_id);

            return $line->fresh('modifiers');
        });
    }

    public function changeLine(Order $order, OrderRound $round, OrderLine $line, int $quantity, User $user): void
    {
        DB::transaction(function () use ($order, $round, $line, $quantity, $user): void {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);
            abort_unless($round->order_id === $order->id && $line->order_round_id === $round->id && $round->isDraft(), 404);
            $this->assertNoPayments($order);
            if ($quantity < 1) {
                $line->delete();
                $type = 'line_removed';
            } else {
                $newQuantity = min($quantity, 99);
                $line->update(['quantity' => $newQuantity, 'line_total_minor' => $newQuantity * $line->unit_total_minor, 'active_line_total_minor' => $newQuantity * ($line->manual_unit_total_minor ?? $line->unit_total_minor)]);
                $type = 'line_changed';
            }
            $round->increment('version');
            $this->refreshTotal($order, $user, $type, $line->employee_id);
        });
    }

    public function submit(Order $order, OrderRound $round, User $user): OrderRound
    {
        return DB::transaction(function () use ($order, $round, $user): OrderRound {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);
            $round = OrderRound::query()->lockForUpdate()->findOrFail($round->id);
            abort_unless($round->order_id === $order->id && $round->isDraft(), 404);
            $this->assertNoPayments($order);
            if (! $round->lines()->exists()) {
                throw new InvalidArgumentException('Añade al menos un producto antes de confirmar.');
            }
            $round->update(['status' => 'submitted', 'draft_slot' => null, 'submitted_at' => now(), 'submitted_by_user_id' => $user->id, 'submitted_by_employee_id' => $order->current_employee_id, 'submission_key' => 'round-'.$round->id]);
            app(KitchenService::class)->dispatchRound($round);
            app(StockService::class)->consumeRound($round->fresh('lines.product'), $user, $order->currentEmployee);
            $order->update(['version' => $order->version + 1]);
            $this->event($order, 'round_submitted', $user, $order->currentEmployee, ['round_id' => $round->id]);

            return $round->fresh('lines.modifiers');
        });
    }

    public function cancelEmpty(Order $order, User $user): void
    {
        DB::transaction(function () use ($order, $user): void {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);
            abort_unless($order->status === 'open' && ! $order->lines()->exists(), 422, 'No se puede cancelar una cuenta con consumo.');
            $order->update(['status' => 'cancelled', 'closed_at' => now(), 'version' => $order->version + 1]);
            ActiveTableOrder::query()->where('order_id', $order->id)->delete();
            $this->event($order, 'cancelled', $user, $order->currentEmployee);
        });
    }

    public function transfer(Order $order, DiningTable $destination, Employee $employee, User $user): Order
    {
        return DB::transaction(function () use ($order, $destination, $employee, $user): Order {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);
            $tables = DiningTable::query()->with('zone')->whereIn('id', [$order->dining_table_id, $destination->id])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $target = $tables->get($destination->id);
            $source = $tables->get($order->dining_table_id);
            abort_unless($target && $target->restaurant_id === $order->restaurant_id && $target->zone?->restaurant_id === $order->restaurant_id, 404);
            if (! $target->is_active || ! $target->zone?->is_active) {
                throw new InvalidArgumentException('La mesa destino no está disponible.');
            }
            if (ActiveTableOrder::query()->where('restaurant_id', $order->restaurant_id)->where('dining_table_id', $target->id)->where('order_id', '<>', $order->id)->exists()) {
                throw new InvalidArgumentException('La mesa seleccionada ya tiene una cuenta abierta.');
            }
            $oldName = $source?->name;
            $newName = $target->name;
            ActiveTableOrder::query()->where('order_id', $order->id)->update(['dining_table_id' => $target->id]);
            $order->update(['dining_table_id' => $target->id, 'version' => $order->version + 1]);
            $this->event($order, 'table_transferred', $user, $employee, ['from_table_id' => $source?->id, 'from_table_name' => $oldName, 'to_table_id' => $target->id, 'to_table_name' => $newName]);

            return $order->fresh(['table.zone', 'currentEmployee']);
        });
    }

    public function voidLine(OrderLine $line, int $quantity, string $reason, Employee $employee, User $user): void
    {
        DB::transaction(function () use ($line, $quantity, $reason, $employee, $user): void {
            $line = OrderLine::query()->lockForUpdate()->findOrFail($line->id);
            $order = Order::query()->lockForUpdate()->findOrFail($line->order_id);
            $this->assertNoPayments($order);
            if ($order->status !== 'open' || $line->round->status !== 'submitted') {
                throw new InvalidArgumentException('Solo se pueden anular líneas consolidadas.');
            }
            if ($quantity < 1 || $quantity > $line->activeQuantity()) {
                throw new InvalidArgumentException('La cantidad a anular no es válida.');
            }
            $before = $line->activeQuantity();
            $line->update(['voided_quantity' => $line->voided_quantity + $quantity, 'active_line_total_minor' => ($before - $quantity) * ($line->manual_unit_total_minor ?? $line->unit_total_minor)]);
            $this->refreshTotal($order, $user, 'line_voided', $employee->id, ['line_id' => $line->id, 'quantity_before' => $before, 'quantity_after' => $before - $quantity, 'amount_minor' => $quantity * ($line->manual_unit_total_minor ?? $line->unit_total_minor), 'reason' => $reason]);
            app(KitchenService::class)->cancellation($line, $quantity, $reason, $employee, $user);
            app(StockService::class)->restoreLine($line->fresh('product'), $quantity, $reason, $user, $employee);
        });
    }

    public function setManualPrice(OrderLine $line, int $priceMinor, string $reason, Employee $employee, User $user): void
    {
        DB::transaction(function () use ($line, $priceMinor, $reason, $employee, $user): void {
            $line = OrderLine::query()->lockForUpdate()->findOrFail($line->id);
            $order = Order::query()->lockForUpdate()->findOrFail($line->order_id);
            $this->assertNoPayments($order);
            if ($priceMinor < 0 || $priceMinor > 100000000 || $line->round->status !== 'draft') {
                throw new InvalidArgumentException('El precio manual no es válido para esta línea.');
            }
            if (! $line->product?->allows_manual_price) {
                throw new InvalidArgumentException('Este producto no permite precio manual.');
            }
            $old = $line->manual_unit_total_minor ?? $line->unit_total_minor;
            $line->update(['manual_unit_total_minor' => $priceMinor, 'active_line_total_minor' => $line->activeQuantity() * $priceMinor]);
            $this->refreshTotal($order, $user, 'manual_price_changed', $employee->id, ['line_id' => $line->id, 'previous_unit_minor' => $old, 'new_unit_minor' => $priceMinor, 'reason' => $reason]);
        });
    }

    public function discount(Order $order, string $kind, int $value, string $reason, Employee $employee, User $user): OrderDiscount
    {
        return DB::transaction(function () use ($order, $kind, $value, $reason, $employee, $user): OrderDiscount {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);
            $this->assertNoPayments($order);
            $subtotal = (int) $order->lines()->sum('active_line_total_minor');
            if (! in_array($kind, ['percentage', 'fixed'], true) || $value < 0 || ($kind === 'percentage' && $value > 10000) || ($kind === 'fixed' && $value > $subtotal)) {
                throw new InvalidArgumentException('El descuento no es válido.');
            }
            $discount = $kind === 'percentage' ? intdiv($subtotal * $value + 5000, 10000) : $value;
            $order->discounts()->where('is_active', true)->update(['is_active' => false]);
            $record = $order->discounts()->create(['restaurant_id' => $order->restaurant_id, 'user_id' => $user->id, 'employee_id' => $employee->id, 'kind' => $kind, 'percentage_basis_points' => $kind === 'percentage' ? $value : null, 'fixed_minor' => $kind === 'fixed' ? $value : null, 'subtotal_minor' => $subtotal, 'discount_minor' => min($discount, $subtotal), 'total_minor' => max(0, $subtotal - $discount), 'reason' => $reason]);
            $this->refreshTotal($order, $user, 'discount_applied', $employee->id, ['discount_id' => $record->id, 'discount_minor' => $record->discount_minor, 'reason' => $reason]);

            return $record;
        });
    }

    public function splitEqual(Order $order, int $parts, Employee $employee, User $user): OrderSplitPlan
    {
        if ($parts < 2 || $parts > 20) {
            throw new InvalidArgumentException('El número de partes debe estar entre 2 y 20.');
        }

        return DB::transaction(function () use ($order, $parts, $employee, $user): OrderSplitPlan {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);
            $this->assertNoPayments($order);
            $total = (int) $order->total_minor;
            $base = intdiv($total, $parts);
            $remainder = $total % $parts;
            $plan = $order->splitPlans()->where('status', 'active')->first();
            if ($plan) {
                throw new InvalidArgumentException('Ya existe una división activa.');
            }
            $plan = $order->splitPlans()->create(['restaurant_id' => $order->restaurant_id, 'user_id' => $user->id, 'employee_id' => $employee->id, 'method' => 'equal', 'status' => 'active', 'source_total_minor' => $total, 'source_order_version' => $order->version]);
            for ($i = 1; $i <= $parts; $i++) {
                $plan->parts()->create(['restaurant_id' => $order->restaurant_id, 'sequence' => $i, 'label' => 'Cuenta '.$i, 'total_minor' => $base + ($remainder > 0 && $i > $parts - $remainder ? 1 : 0), 'status' => 'pending']);
            }
            $plan->update(['status' => 'active']);
            $this->event($order, 'split_prepared', $user, $employee, ['plan_id' => $plan->id, 'method' => 'equal', 'parts' => $parts]);

            return $plan->load('parts');
        });
    }

    public function splitProducts(Order $order, array $allocations, Employee $employee, User $user): OrderSplitPlan
    {
        return DB::transaction(function () use ($order, $allocations, $employee, $user): OrderSplitPlan {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);
            $this->assertNoPayments($order);
            $active = $order->lines()->with('modifiers')->get()->keyBy('id');
            $assigned = [];
            foreach ($allocations as $partIndex => $items) {
                foreach ($items as $lineId => $quantity) {
                    $line = $active->get((int) $lineId);
                    $quantity = (int) $quantity;
                    if (! $line || $quantity < 1 || $quantity > $line->activeQuantity() || ($assigned[$line->id] ?? 0) + $quantity > $line->activeQuantity()) {
                        throw new InvalidArgumentException('La selección del split no es válida.');
                    } $assigned[$line->id] = ($assigned[$line->id] ?? 0) + $quantity;
                }
            }
            $plan = $order->splitPlans()->where('status', 'active')->first();
            if ($plan) {
                throw new InvalidArgumentException('Ya existe una división activa.');
            }
            $plan = $order->splitPlans()->create(['restaurant_id' => $order->restaurant_id, 'user_id' => $user->id, 'employee_id' => $employee->id, 'method' => 'products', 'status' => 'active', 'source_total_minor' => $order->total_minor, 'source_order_version' => $order->version]);
            $createdParts = [];
            $rawTotals = [];
            foreach ($allocations as $partIndex => $items) {
                $part = $plan->parts()->create(['restaurant_id' => $order->restaurant_id, 'sequence' => $partIndex + 1, 'label' => 'Cuenta '.($partIndex + 1), 'total_minor' => 0, 'status' => 'pending']);
                $raw = 0;
                foreach ($items as $lineId => $quantity) {
                    $line = $active->get((int) $lineId);
                    $amount = $quantity * ($line->manual_unit_total_minor ?? $line->unit_total_minor);
                    $raw += $amount;
                    $part->allocations()->create(['restaurant_id' => $order->restaurant_id, 'order_line_id' => $line->id, 'quantity' => $quantity, 'total_minor' => $amount]);
                }
                $createdParts[$partIndex] = $part;
                $rawTotals[$partIndex] = $raw;
            }
            $rawTotal = array_sum($rawTotals);
            $discount = (int) $order->discounts()->where('is_active', true)->value('discount_minor');
            $subtotal = (int) $order->lines()->sum('active_line_total_minor');
            $discountForParts = intdiv(min($discount, $subtotal) * $rawTotal + intdiv($subtotal, 2), max(1, $subtotal));
            $discountShares = [];
            $fractional = [];
            foreach ($rawTotals as $partIndex => $raw) {
                $numerator = $discountForParts * $raw;
                $share = intdiv($numerator, max(1, $rawTotal));
                $discountShares[$partIndex] = $share;
                $fractional[$partIndex] = $numerator % max(1, $rawTotal);
            }
            $remainingDiscount = $discountForParts - array_sum($discountShares);
            arsort($fractional);
            foreach (array_keys($fractional) as $partIndex) {
                if ($remainingDiscount < 1) {
                    break;
                }
                $discountShares[$partIndex]++;
                $remainingDiscount--;
            }
            foreach ($createdParts as $partIndex => $part) {
                $part->update(['total_minor' => max(0, $rawTotals[$partIndex] - ($discountShares[$partIndex] ?? 0))]);
            }
            $allocatedTotal = array_sum(array_map(fn ($part) => (int) $part->total_minor, $createdParts));
            if ($allocatedTotal < (int) $order->total_minor) {
                $plan->parts()->create(['restaurant_id' => $order->restaurant_id, 'sequence' => count($createdParts) + 1, 'label' => 'Resto de la cuenta', 'total_minor' => $order->total_minor - $allocatedTotal, 'status' => 'pending']);
            }
            $this->event($order, 'split_prepared', $user, $employee, ['plan_id' => $plan->id, 'method' => 'products']);

            return $plan->load('parts.allocations.line');
        });
    }

    public function cancelSplit(OrderSplitPlan $plan, User $user, Employee $employee): void
    {
        DB::transaction(function () use ($plan, $user, $employee): void {
            $plan = OrderSplitPlan::query()->lockForUpdate()->findOrFail($plan->id);
            if ($plan->payments()->where('status', 'succeeded')->exists() || $plan->parts()->whereHas('payments', fn ($query) => $query->where('status', 'succeeded'))->exists()) {
                throw new InvalidArgumentException('No se puede cancelar un split con pagos.');
            }
            $plan->update(['status' => 'cancelled', 'version' => $plan->version + 1]);
            $this->event($plan->order, 'split_cancelled', $user, $employee, ['plan_id' => $plan->id]);
        });
    }

    public function recover(Order $order, DiningTable $destination, Employee $employee, User $user): Order
    {
        return DB::transaction(function () use ($order, $destination, $employee, $user): Order {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);
            abort_unless($order->status === 'cancelled' && $order->restaurant_id === $destination->restaurant_id, 404);
            $destination = DiningTable::query()->with('zone')->lockForUpdate()->findOrFail($destination->id);
            if (! $destination->is_active || ! $destination->zone?->is_active || ActiveTableOrder::query()->where('dining_table_id', $destination->id)->exists()) {
                throw new InvalidArgumentException('La mesa seleccionada no está disponible.');
            }
            $order->update(['status' => 'open', 'closed_at' => null, 'dining_table_id' => $destination->id, 'current_employee_id' => $employee->id, 'version' => $order->version + 1]);
            ActiveTableOrder::create(['restaurant_id' => $order->restaurant_id, 'dining_table_id' => $destination->id, 'order_id' => $order->id]);
            if (! $order->rounds()->where('draft_slot', 1)->exists()) {
                $order->rounds()->create(['restaurant_id' => $order->restaurant_id, 'sequence' => ((int) $order->rounds()->max('sequence')) + 1, 'draft_slot' => 1, 'status' => 'draft', 'created_by_user_id' => $user->id, 'created_by_employee_id' => $employee->id]);
            }
            $this->event($order, 'recovered', $user, $employee, ['destination_table_id' => $destination->id, 'reason' => 'Recuperación administrativa']);

            return $order->fresh(['table.zone', 'currentEmployee']);
        });
    }

    private function refreshTotal(Order $order, User $user, string $event, ?int $employeeId, array $data = []): void
    {
        $subtotal = (int) $order->lines()->sum('active_line_total_minor');
        $discount = (int) $order->discounts()->where('is_active', true)->value('discount_minor');
        $charges = (int) $order->charges()->sum('amount_minor');
        $order->update(['total_minor' => max(0, $subtotal - min($subtotal, $discount)) + $charges, 'version' => $order->version + 1]);
        $this->event($order, $event, $user, $employeeId ? Employee::find($employeeId) : null, $data + ['subtotal_minor' => $subtotal, 'total_minor' => $order->total_minor]);
    }

    private function assertNoPayments(Order $order): void
    {
        if ($order->payments()->where('status', 'succeeded')->exists()) {
            throw new InvalidArgumentException('La cuenta está en proceso de cobro y ya no admite cambios.');
        }
    }

    private function discountedShare(Order $order, int $raw): int
    {
        $subtotal = (int) $order->lines()->sum('active_line_total_minor');
        $discount = (int) $order->discounts()->where('is_active', true)->value('discount_minor');

        return max(0, $raw - intdiv($discount * $raw + intdiv($subtotal, 2), max(1, $subtotal)));
    }

    private function event(Order $order, string $type, User $user, ?Employee $employee, array $data = []): void
    {
        OrderEvent::create(['restaurant_id' => $order->restaurant_id, 'order_id' => $order->id, 'user_id' => $user->id, 'employee_id' => $employee?->id, 'type' => $type, 'data' => $data]);
    }

    private function normalizeSelections(array $selections): array
    {
        $normalized = [];
        foreach ($selections as $assignmentId => $options) {
            if (! is_array($options)) {
                continue;
            }
            foreach ($options as $optionId => $quantity) {
                $quantity = (int) $quantity;
                if ($quantity > 0) {
                    $normalized[(int) $assignmentId][(int) $optionId] = $quantity;
                }
            }
        }

        return $normalized;
    }

    private function validateSelections($product, array $selections): void
    {
        $validAssignments = $product->modifierGroupAssignments->keyBy('id');
        foreach ($selections as $assignmentId => $options) {
            $assignment = $validAssignments->get($assignmentId);
            if (! $assignment || ! $assignment->group->is_active) {
                throw new InvalidArgumentException('La configuración del producto ya no es válida.');
            }
            foreach ($options as $optionId => $quantity) {
                if ($quantity === 0) {
                    unset($selections[$assignmentId][$optionId]);
                }
            }
        }
        foreach ($validAssignments as $assignment) {
            if (! $assignment->group->is_active) {
                continue;
            }
            $selected = $selections[$assignment->id] ?? [];
            if (! $assignment->group->allow_quantities && collect($selected)->contains(fn ($quantity) => $quantity > 1)) {
                throw new InvalidArgumentException('Este grupo no permite cantidades.');
            }
        }
    }

    private function snapshot(Order $order, $product, $format, array $price, int $quantity, ?string $notes, string $key, array $selections): array
    {
        $modifiers = [];
        $position = 0;
        foreach ($product->modifierGroupAssignments as $assignment) {
            foreach ($selections[$assignment->id] ?? [] as $optionId => $selectedQuantity) {
                $option = $assignment->group->options->firstWhere('id', (int) $optionId);
                if (! $option) {
                    continue;
                }
                $modifiers[] = ['product_modifier_group_id' => $assignment->id, 'modifier_group_id' => $assignment->modifier_group_id, 'modifier_option_id' => $option->id, 'group_name' => $assignment->group->name, 'option_name' => $option->name, 'instruction' => $option->instruction, 'quantity' => $selectedQuantity, 'unit_delta_minor' => $option->price_delta_minor, 'total_delta_minor' => $option->price_delta_minor * $selectedQuantity * $quantity, 'position' => $position++];
            }
        }

        return ['schema_version' => 1, 'configuration_key' => $key, 'captured_at' => now()->toISOString(), 'product' => ['id' => $product->id, 'name' => $product->name, 'short_name' => $product->short_name, 'category' => ['id' => $product->category_id, 'name' => $product->category->name]], 'format' => ['id' => $format?->id, 'name' => $price['format_name']], 'quantity' => $quantity, 'currency' => $price['currency'], 'vat_rate' => $price['vat_rate'], 'notes' => $notes, 'modifiers' => $modifiers, 'unit_base_minor' => $price['base_minor'], 'unit_modifiers_minor' => $price['total_minor'] - $price['base_minor'], 'unit_total_minor' => $price['total_minor'], 'line_total_minor' => $price['total_minor'] * $quantity];
    }
}
