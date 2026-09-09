<?php

namespace App;

use App\Models\Customer;
use App\Models\Employee;
use App\Models\Order;
use App\Models\SaleDocument;
use App\Models\SaleDocumentCounter;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class BillingService
{
    public function ensureTicket(Order $order, User $user, ?Employee $employee = null): SaleDocument
    {
        $order = Order::query()->with(['lines', 'payments.tenders.method', 'table.zone', 'customer', 'currentEmployee', 'discounts', 'charges'])->findOrFail($order->id);
        $this->assertBillable($order);
        $existing = SaleDocument::query()->where('order_id', $order->id)->where('kind', 'ticket')->first();
        if ($existing) {
            return $existing;
        }

        return $this->createDocument($order, 'ticket', null, $user, $employee);
    }

    public function createInvoice(Order $order, Customer $customer, User $user, ?Employee $employee = null): SaleDocument
    {
        $order = Order::query()->with(['lines', 'payments.tenders.method', 'table.zone', 'customer', 'currentEmployee', 'discounts', 'charges'])->findOrFail($order->id);
        $this->assertBillable($order);
        abort_unless($customer->restaurant_id === $order->restaurant_id, 404);
        if (! filled($customer->tax_id)) {
            throw new InvalidArgumentException('El cliente necesita NIF/CIF para emitir factura.');
        }
        $existing = SaleDocument::query()->where('order_id', $order->id)->where('kind', 'invoice')->first();
        if ($existing) {
            return $existing;
        }

        return $this->createDocument($order, 'invoice', $customer, $user, $employee);
    }

    private function assertBillable(Order $order): void
    {
        if ($order->status !== 'paid' && $order->payment_status !== 'paid') {
            throw new InvalidArgumentException('Solo se puede documentar una venta cerrada y cobrada.');
        }
    }

    private function createDocument(Order $order, string $kind, ?Customer $customer, User $user, ?Employee $employee): SaleDocument
    {
        try {
            return DB::transaction(function () use ($order, $kind, $customer, $user, $employee): SaleDocument {
                $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);
                $existing = SaleDocument::query()->where('order_id', $lockedOrder->id)->where('kind', $kind)->first();
                if ($existing) {
                    return $existing;
                }
                $counter = SaleDocumentCounter::query()->lockForUpdate()->firstOrCreate(
                    ['restaurant_id' => $lockedOrder->restaurant_id, 'kind' => $kind],
                    ['last_number' => 0]
                );
                $number = $counter->last_number + 1;
                $counter->update(['last_number' => $number]);

                $totals = $this->totals($lockedOrder);
                $snapshot = $this->snapshot($lockedOrder->fresh(['lines', 'payments.tenders.method', 'table.zone', 'customer', 'currentEmployee', 'discounts', 'charges']), $customer, $totals);
                $year = CarbonImmutable::now($lockedOrder->restaurant->timezone)->year;

                $document = SaleDocument::create([
                    'restaurant_id' => $lockedOrder->restaurant_id, 'order_id' => $lockedOrder->id,
                    'customer_id' => $customer?->id ?? $lockedOrder->customer_id,
                    'issued_by_user_id' => $user->id, 'issued_by_employee_id' => $employee?->id,
                    'kind' => $kind, 'number' => $number,
                    'reference' => ($kind === 'invoice' ? 'F' : 'T').'-'.$year.'-'.str_pad((string) $number, 6, '0', STR_PAD_LEFT),
                    'currency' => $lockedOrder->currency,
                    'subtotal_minor' => $totals['subtotal'], 'discount_minor' => $totals['discount'],
                    'charges_minor' => $totals['charges'], 'total_minor' => $totals['total'],
                    'tax_breakdown' => $totals['taxes'], 'snapshot' => $snapshot,
                    'status' => 'issued', 'issued_at' => now(),
                ]);
                DB::afterCommit(function () use ($document): void {
                    $fresh = $document->fresh();
                    app(FiscalService::class)->registerForDocument($fresh);
                    app(OutboundWebhookService::class)->fire($fresh->restaurant, 'invoice.created', ['document_id' => $fresh->id, 'reference' => $fresh->reference, 'total_minor' => $fresh->total_minor, 'order_id' => $fresh->order_id]);
                });

                return $document;
            });
        } catch (QueryException $exception) {
            $existing = SaleDocument::query()->where('order_id', $order->id)->where('kind', $kind)->first();
            if ($existing) {
                return $existing;
            }

            throw $exception;
        }
    }

    /** @return array{subtotal:int,discount:int,charges:int,total:int,taxes:array,net_lines:array} */
    public function totals(Order $order): array
    {
        $lines = $order->lines()->orderBy('position')->orderBy('id')->get();
        $subtotal = (int) $lines->sum('active_line_total_minor');
        $discount = min($subtotal, (int) $order->discounts()->where('is_active', true)->value('discount_minor'));
        $charges = (int) $order->charges()->sum('amount_minor');
        $shares = $this->discountShares($lines->map(fn ($line) => (int) $line->active_line_total_minor)->all(), $discount);

        $netLines = [];
        foreach ($lines->values() as $index => $line) {
            if ((int) $line->active_line_total_minor < 1 && (int) $line->voided_quantity < 1) {
                continue;
            }
            $net = max(0, (int) $line->active_line_total_minor - ($shares[$index] ?? 0));
            if ($net < 1 && (int) $line->active_line_total_minor < 1) {
                continue;
            }
            $netLines[] = ['line' => $line, 'net_minor' => $net];
        }

        $taxes = [];
        foreach ($netLines as $item) {
            $rate = $item['line']->vat_rate;
            $key = $rate === null ? 'sin-iva' : number_format((float) $rate, 2, '.', '');
            $gross = $item['net_minor'];
            $bps = $rate === null ? 0 : (int) round(((float) $rate) * 100);
            $divisor = 10000 + $bps;
            $base = intdiv($gross * 10000 + intdiv($divisor, 2), $divisor);
            $taxes[$key] ??= ['rate' => $rate, 'gross_minor' => 0, 'base_minor' => 0, 'tax_minor' => 0];
            $taxes[$key]['gross_minor'] += $gross;
            $taxes[$key]['base_minor'] += $base;
            $taxes[$key]['tax_minor'] += $gross - $base;
        }
        if ($charges > 0) {
            $rate = $order->restaurant->default_vat;
            $key = $rate === null ? 'sin-iva' : number_format((float) $rate, 2, '.', '');
            $bps = $rate === null ? 0 : (int) round(((float) $rate) * 100);
            $divisor = 10000 + $bps;
            $base = intdiv($charges * 10000 + intdiv($divisor, 2), $divisor);
            $taxes[$key] ??= ['rate' => $rate, 'gross_minor' => 0, 'base_minor' => 0, 'tax_minor' => 0];
            $taxes[$key]['gross_minor'] += $charges;
            $taxes[$key]['base_minor'] += $base;
            $taxes[$key]['tax_minor'] += $charges - $base;
        }

        return [
            'subtotal' => $subtotal, 'discount' => $discount, 'charges' => $charges,
            'total' => max(0, $subtotal - $discount) + $charges,
            'taxes' => array_values($taxes), 'net_lines' => $netLines,
        ];
    }

    private function discountShares(array $amounts, int $discount): array
    {
        $total = array_sum($amounts);
        if ($total < 1 || $discount < 1) {
            return array_fill(0, count($amounts), 0);
        }
        $shares = [];
        $remainders = [];
        foreach ($amounts as $index => $amount) {
            $shares[$index] = intdiv($discount * $amount, $total);
            $remainders[$index] = ($discount * $amount) % $total;
        }
        $pending = $discount - array_sum($shares);
        arsort($remainders);
        foreach (array_keys($remainders) as $index) {
            if ($pending < 1) {
                break;
            }
            $shares[$index]++;
            $pending--;
        }

        return $shares;
    }

    private function snapshot(Order $order, ?Customer $customer, array $totals): array
    {
        $restaurant = $order->restaurant;
        $customer ??= $order->customer;

        return [
            'schema_version' => 1,
            'captured_at' => now()->toISOString(),
            'restaurant' => [
                'id' => $restaurant->id, 'name' => $restaurant->name, 'legal_name' => $restaurant->legal_name,
                'tax_id' => $restaurant->tax_id, 'address' => $restaurant->address, 'postal_code' => $restaurant->postal_code,
                'city' => $restaurant->city, 'province' => $restaurant->province, 'country' => $restaurant->country,
                'currency' => $restaurant->currency, 'ticket_footer' => $restaurant->ticket_footer,
            ],
            'customer' => $customer ? [
                'id' => $customer->id, 'display_name' => $customer->display_name, 'legal_name' => $customer->legal_name,
                'tax_id' => $customer->tax_id, 'fiscal_address' => $customer->fiscal_address, 'postal_code' => $customer->postal_code,
                'city' => $customer->city, 'province' => $customer->province, 'country' => $customer->country,
                'email' => $customer->email, 'phone' => $customer->phone,
            ] : null,
            'order' => [
                'id' => $order->id, 'channel' => $order->channel, 'origin' => $order->origin,
                'table' => $order->table?->name, 'zone' => $order->table?->zone?->name,
                'guest_count' => $order->guest_count, 'business_date' => $order->business_date?->toDateString(),
                'opened_at' => $order->opened_at?->toISOString(), 'paid_at' => $order->paid_at?->toISOString(),
                'employee' => $order->currentEmployee?->display_name,
                'fulfillment' => $order->fulfillment ? [
                    'customer_name' => $order->fulfillment->customer_name, 'customer_phone' => $order->fulfillment->customer_phone,
                ] : null,
            ],
            'lines' => collect($totals['net_lines'])->map(fn ($item) => [
                'product_name' => $item['line']->product_name, 'format_name' => $item['line']->format_name,
                'quantity' => $item['line']->quantity, 'voided_quantity' => $item['line']->voided_quantity,
                'active_quantity' => $item['line']->activeQuantity(), 'unit_total_minor' => $item['line']->unit_total_minor,
                'gross_minor' => (int) $item['line']->active_line_total_minor, 'net_minor' => $item['net_minor'],
                'vat_rate' => $item['line']->vat_rate, 'currency' => $item['line']->currency,
                'modifiers' => collect($item['line']->modifiers ?? [])->map(fn ($modifier) => [
                    'group' => $modifier['group_name'] ?? $modifier->group_name ?? null,
                    'option' => $modifier['option_name'] ?? $modifier->option_name ?? null,
                    'quantity' => $modifier['quantity'] ?? $modifier->quantity ?? null,
                ])->values()->all(),
            ])->values()->all(),
            'payments' => $order->payments->where('status', 'succeeded')->map(fn ($payment) => [
                'amount_minor' => $payment->amount_minor, 'currency' => $payment->currency,
                'tenders' => $payment->tenders->map(fn ($tender) => [
                    'method' => $tender->method?->name, 'is_cash' => (bool) $tender->method?->is_cash,
                    'amount_minor' => $tender->amount_minor,
                ])->values()->all(),
            ])->values()->all(),
            'totals' => [
                'subtotal_minor' => $totals['subtotal'], 'discount_minor' => $totals['discount'],
                'charges_minor' => $totals['charges'], 'total_minor' => $totals['total'], 'taxes' => $totals['taxes'],
            ],
        ];
    }
}
