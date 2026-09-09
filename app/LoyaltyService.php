<?php

namespace App;

use App\Models\Employee;
use App\Models\LoyaltyEvent;
use App\Models\LoyaltyProgram;
use App\Models\LoyaltyProgress;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class LoyaltyService
{
    public function accrueForOrder(Order $incoming): void
    {
        DB::transaction(function () use ($incoming): void {
            $order = Order::query()->with(['lines.product', 'customer'])->lockForUpdate()->findOrFail($incoming->id);
            if (! $order->customer_id || $order->status !== 'paid') {
                return;
            }
            $programs = $order->restaurant->loyaltyPrograms()->where('is_active', true)->get();
            foreach ($programs as $program) {
                $stamps = $this->matchingStamps($order, $program);
                if ($stamps < 1) {
                    continue;
                }
                if (LoyaltyEvent::query()->where('loyalty_program_id', $program->id)->where('order_id', $order->id)->where('kind', 'accrue')->exists()) {
                    continue;
                }
                $progress = LoyaltyProgress::query()->lockForUpdate()->firstOrCreate(
                    ['loyalty_program_id' => $program->id, 'customer_id' => $order->customer_id],
                    ['stamps' => 0, 'rewards_available' => 0]
                );
                $stamps += $progress->stamps;
                $rewards = $progress->rewards_available + intdiv($stamps, $program->goal);
                $progress->update(['stamps' => $stamps % $program->goal, 'rewards_available' => $rewards]);
                LoyaltyEvent::create(['restaurant_id' => $order->restaurant_id, 'loyalty_program_id' => $program->id, 'customer_id' => $order->customer_id, 'order_id' => $order->id, 'kind' => 'accrue', 'quantity' => $this->matchingStamps($order, $program)]);
            }
        });
    }

    public function revertForOrder(Order $incoming, ?User $user = null, ?Employee $employee = null): void
    {
        DB::transaction(function () use ($incoming, $user, $employee): void {
            $order = Order::query()->lockForUpdate()->findOrFail($incoming->id);
            $accrues = LoyaltyEvent::query()->where('order_id', $order->id)->where('kind', 'accrue')->get();
            foreach ($accrues as $accrue) {
                if (LoyaltyEvent::query()->where('order_id', $order->id)->where('loyalty_program_id', $accrue->loyalty_program_id)->where('kind', 'revert_accrue')->exists()) {
                    continue;
                }
                $progress = LoyaltyProgress::query()->lockForUpdate()->where('loyalty_program_id', $accrue->loyalty_program_id)->where('customer_id', $accrue->customer_id)->first();
                if ($progress) {
                    $progress->update(['stamps' => max(0, $progress->stamps - $accrue->quantity)]);
                }
                LoyaltyEvent::create(['restaurant_id' => $order->restaurant_id, 'loyalty_program_id' => $accrue->loyalty_program_id, 'customer_id' => $accrue->customer_id, 'order_id' => $order->id, 'kind' => 'revert_accrue', 'quantity' => -$accrue->quantity, 'user_id' => $user?->id, 'employee_id' => $employee?->id]);
            }
        });
    }

    public function redeem(Order $incoming, LoyaltyProgram $program, User $user, Employee $employee): Order
    {
        return DB::transaction(function () use ($incoming, $program, $user, $employee): Order {
            $order = Order::query()->lockForUpdate()->findOrFail($incoming->id);
            abort_unless($order->restaurant_id === $program->restaurant_id && $program->is_active, 404);
            abort_unless($order->status === 'open' && $order->customer_id, 422, 'La recompensa requiere cuenta abierta con cliente identificado.');
            if ($order->payments()->where('status', 'succeeded')->exists()) {
                throw new InvalidArgumentException('La cuenta ya ha iniciado el cobro.');
            }
            $progress = LoyaltyProgress::query()->lockForUpdate()->where('loyalty_program_id', $program->id)->where('customer_id', $order->customer_id)->first();
            if (! $progress || $progress->rewards_available < 1) {
                throw new InvalidArgumentException('El cliente no tiene recompensas disponibles.');
            }
            $reward = $program->rewardProduct;
            if (! $reward || $reward->restaurant_id !== $order->restaurant_id) {
                throw new InvalidArgumentException('El programa no tiene recompensa configurada.');
            }
            $round = $order->rounds()->where('draft_slot', 1)->lockForUpdate()->first();
            if (! $round) {
                $round = $order->rounds()->create(['restaurant_id' => $order->restaurant_id, 'sequence' => ((int) $order->rounds()->max('sequence')) + 1, 'draft_slot' => 1, 'status' => 'draft', 'created_by_user_id' => $user->id, 'created_by_employee_id' => $employee->id]);
            }
            $position = ((int) $round->lines()->max('position')) + 10;
            $round->lines()->create([
                'restaurant_id' => $order->restaurant_id, 'order_id' => $order->id, 'product_id' => $reward->id,
                'employee_id' => $employee->id, 'user_id' => $user->id, 'product_name' => $reward->name.' (recompensa)',
                'quantity' => 1, 'voided_quantity' => 0, 'unit_base_minor' => 0, 'unit_modifiers_minor' => 0,
                'unit_total_minor' => 0, 'cost_minor' => $reward->cost_minor, 'line_total_minor' => 0, 'active_line_total_minor' => 0,
                'vat_rate' => $reward->effectiveVat(), 'currency' => $order->currency,
                'snapshot' => ['schema_version' => 1, 'loyalty_reward' => true, 'program_id' => $program->id, 'product' => ['id' => $reward->id, 'name' => $reward->name]],
                'position' => $position,
            ]);
            $progress->update(['rewards_available' => $progress->rewards_available - 1]);
            LoyaltyEvent::create(['restaurant_id' => $order->restaurant_id, 'loyalty_program_id' => $program->id, 'customer_id' => $order->customer_id, 'order_id' => $order->id, 'kind' => 'redeem', 'quantity' => -1, 'user_id' => $user->id, 'employee_id' => $employee->id]);
            $order->events()->create(['restaurant_id' => $order->restaurant_id, 'user_id' => $user->id, 'employee_id' => $employee->id, 'type' => 'loyalty_redeemed', 'data' => ['program_id' => $program->id, 'product' => $reward->name]]);

            return $order->fresh();
        });
    }

    private function matchingStamps(Order $order, LoyaltyProgram $program): int
    {
        $stamps = 0;
        foreach ($order->lines as $line) {
            if ($line->activeQuantity() < 1 || ($line->snapshot['loyalty_reward'] ?? false)) {
                continue;
            }
            if ($program->target_type === 'product' && (int) $line->product_id === (int) $program->target_id) {
                $stamps += $line->activeQuantity();
            } elseif ($program->target_type === 'category' && (int) ($line->product?->category_id ?? 0) === (int) $program->target_id) {
                $stamps += $line->activeQuantity();
            }
        }

        return $stamps;
    }
}
