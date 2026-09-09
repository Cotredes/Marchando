<?php

namespace App;

use App\Models\CashDrawerEvent;
use App\Models\CashMovement;
use App\Models\CouponRedemption;
use App\Models\ExportLog;
use App\Models\FiscalRecord;
use App\Models\LoyaltyEvent;
use App\Models\OnlineRefund;
use App\Models\OrderEvent;
use App\Models\PaymentReversal;
use App\Models\PrintJob;
use App\Models\Restaurant;
use App\Models\StockMovement;
use App\Models\WorkIntervalCorrection;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class AuditService
{
    /** @return Collection<int, array<string, mixed>> */
    public function entries(Restaurant $restaurant, array $filters = [], int $limit = 200): Collection
    {
        $from = $filters['from'] ?? null;
        $to = $filters['to'] ?? null;
        $employeeId = $filters['employee_id'] ?? null;
        $module = $filters['module'] ?? null;
        $type = $filters['type'] ?? null;

        $entries = collect();
        if (! $module || $module === 'ventas') {
            $events = OrderEvent::query()->with(['order.table', 'employee'])
                ->where('restaurant_id', $restaurant->id)
                ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
                ->when($to, fn ($q) => $q->where('created_at', '<=', $to))
                ->when($employeeId, fn ($q) => $q->where('employee_id', $employeeId))
                ->when($type, fn ($q) => $q->where('type', $type))
                ->latest('id')->limit($limit)->get();
            foreach ($events as $event) {
                $entries->push([
                    'at' => $event->created_at, 'module' => 'ventas',
                    'actor' => $event->employee?->display_name ?? 'Personal',
                    'action' => $this->orderEventText($event),
                    'detail' => $this->orderEventDetail($event),
                    'order_id' => $event->order_id, 'amount_minor' => $event->data['amount_minor'] ?? $event->data['discount_minor'] ?? null,
                ]);
            }
            $reversals = PaymentReversal::query()->with('payment.order')
                ->where('restaurant_id', $restaurant->id)
                ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
                ->when($to, fn ($q) => $q->where('created_at', '<=', $to))
                ->when($employeeId, fn ($q) => $q->where('employee_id', $employeeId))
                ->latest('id')->limit($limit)->get();
            foreach ($reversals as $reversal) {
                $entries->push([
                    'at' => $reversal->created_at, 'module' => 'ventas',
                    'actor' => $reversal->employee_id ? 'Empleado #'.$reversal->employee_id : 'Personal',
                    'action' => 'Anuló un cobro de '.CatalogMoney::format($reversal->amount_minor).' €',
                    'detail' => 'Motivo: '.($reversal->reason ?? '—'),
                    'order_id' => $reversal->payment?->order_id, 'amount_minor' => $reversal->amount_minor,
                ]);
            }
        }
        if (! $module || $module === 'caja') {
            $movements = CashMovement::query()->with('session.register')
                ->where('restaurant_id', $restaurant->id)
                ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
                ->when($to, fn ($q) => $q->where('created_at', '<=', $to))
                ->when($employeeId, fn ($q) => $q->where('employee_id', $employeeId))
                ->when($type, fn ($q) => $q->where('type', $type))
                ->latest('id')->limit($limit)->get();
            foreach ($movements as $movement) {
                $entries->push([
                    'at' => $movement->created_at, 'module' => 'caja',
                    'actor' => $movement->employee_id ? 'Empleado #'.$movement->employee_id : 'Personal',
                    'action' => match ($movement->type) {
                        'sale' => 'Cobro en efectivo de '.CatalogMoney::format($movement->amount_minor).' €',
                        'paid_in' => 'Ingreso en caja de '.CatalogMoney::format($movement->amount_minor).' €',
                        'paid_out' => 'Retirada de caja de '.CatalogMoney::format($movement->amount_minor).' €',
                        default => 'Movimiento de caja ('.$movement->type.') de '.CatalogMoney::format($movement->amount_minor).' €',
                    },
                    'detail' => $movement->reason ? 'Motivo: '.$movement->reason : null,
                    'order_id' => $movement->payment?->order_id, 'amount_minor' => $movement->amount_minor,
                ]);
            }
        }
        if (! $module || $module === 'stock') {
            $stocks = StockMovement::query()->with('product')
                ->where('restaurant_id', $restaurant->id)
                ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
                ->when($to, fn ($q) => $q->where('created_at', '<=', $to))
                ->when($employeeId, fn ($q) => $q->where('employee_id', $employeeId))
                ->when($type, fn ($q) => $q->where('type', $type))
                ->latest('id')->limit($limit)->get();
            foreach ($stocks as $stock) {
                if (in_array($stock->type, ['sale'], true)) {
                    continue;
                }
                $entries->push([
                    'at' => $stock->created_at, 'module' => 'stock',
                    'actor' => $stock->employee?->display_name ?? 'Personal',
                    'action' => match ($stock->type) {
                        'initial' => 'Stock inicial de '.$stock->product?->name.': '.$stock->resulting_quantity,
                        'entry' => 'Entrada de '.$stock->quantity_delta.' × '.($stock->product?->name ?? 'producto'),
                        'adjust_in', 'adjust_out' => 'Ajuste de stock ('.($stock->quantity_delta > 0 ? '+' : '').$stock->quantity_delta.') en '.($stock->product?->name ?? 'producto'),
                        'sale_reversal' => 'Devolución a stock de '.$stock->quantity_delta.' × '.($stock->product?->name ?? 'producto'),
                        default => 'Movimiento de stock ('.$stock->type.')',
                    },
                    'detail' => $stock->reason ? 'Motivo: '.$stock->reason : null,
                    'order_id' => $stock->order_id, 'amount_minor' => null,
                ]);
            }
        }
        if (! $module || $module === 'personal') {
            $corrections = WorkIntervalCorrection::query()
                ->where('restaurant_id', $restaurant->id)
                ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
                ->when($to, fn ($q) => $q->where('created_at', '<=', $to))
                ->latest('id')->limit($limit)->get();
            foreach ($corrections as $correction) {
                $entries->push([
                    'at' => $correction->created_at, 'module' => 'personal',
                    'actor' => $correction->actor_name ?? 'Administración',
                    'action' => 'Corrección de fichaje',
                    'detail' => $correction->reason ? 'Motivo: '.$correction->reason : null,
                    'order_id' => null, 'amount_minor' => null,
                ]);
            }
        }
        if (! $module || $module === 'impresion') {
            $jobs = PrintJob::query()->with(['printer', 'connector'])
                ->where('restaurant_id', $restaurant->id)
                ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
                ->when($to, fn ($q) => $q->where('created_at', '<=', $to))
                ->when($employeeId, fn ($q) => $q->where('created_by_employee_id', $employeeId))
                ->latest('id')->limit($limit)->get();
            foreach ($jobs as $job) {
                if ($job->kind === 'kitchen_ticket' && $job->status === 'printed' && ! $job->is_reprint) {
                    continue;
                }
                $entries->push([
                    'at' => $job->created_at, 'module' => 'impresion',
                    'actor' => $job->created_by_employee_id ? 'Empleado #'.$job->created_by_employee_id : 'Sistema',
                    'action' => match (true) {
                        $job->is_reprint => 'Reimprimió '.$job->kind.' en '.($job->printer?->name ?? 'impresora'),
                        $job->status === 'error' => 'Fallo al imprimir '.$job->kind.' en '.($job->printer?->name ?? 'impresora'),
                        default => 'Imprimió '.$job->kind.' en '.($job->printer?->name ?? 'impresora'),
                    },
                    'detail' => $job->error ?? ($job->is_reprint ? 'REIMPRESIÓN marcada en papel' : null),
                    'order_id' => $job->order_id, 'amount_minor' => null,
                ]);
            }
            $drawers = CashDrawerEvent::query()->with('employee')
                ->where('restaurant_id', $restaurant->id)
                ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
                ->when($to, fn ($q) => $q->where('created_at', '<=', $to))
                ->when($employeeId, fn ($q) => $q->where('employee_id', $employeeId))
                ->latest('id')->limit($limit)->get();
            foreach ($drawers as $drawer) {
                $entries->push([
                    'at' => $drawer->created_at, 'module' => 'impresion',
                    'actor' => $drawer->employee?->display_name ?? 'Personal',
                    'action' => ($drawer->trigger === 'manual' ? 'Apertura manual de cajón' : 'Apertura de cajón por cobro').' ('.$drawer->status.')',
                    'detail' => $drawer->error,
                    'order_id' => null, 'amount_minor' => null,
                ]);
            }
        }
        if (! $module || $module === 'fiscal') {
            $records = FiscalRecord::query()->with('identity')
                ->where('restaurant_id', $restaurant->id)
                ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
                ->when($to, fn ($q) => $q->where('created_at', '<=', $to))
                ->latest('id')->limit($limit)->get();
            foreach ($records as $record) {
                $entries->push([
                    'at' => $record->created_at, 'module' => 'fiscal',
                    'actor' => 'Sistema',
                    'action' => ($record->record_type === 'anulacion' ? 'Anulación fiscal ' : 'Alta fiscal ').$record->serie.'/'.$record->numero.' ('.$record->status.')',
                    'detail' => 'Obligado '.$record->identity?->nif.' · entorno '.$record->environment,
                    'order_id' => $record->document?->order_id, 'amount_minor' => $record->total_minor,
                ]);
            }
        }
        if (! $module || $module === 'online') {
            $refunds = OnlineRefund::query()
                ->where('restaurant_id', $restaurant->id)
                ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
                ->when($to, fn ($q) => $q->where('created_at', '<=', $to))
                ->when($employeeId, fn ($q) => $q->where('employee_id', $employeeId))
                ->latest('id')->limit($limit)->get();
            foreach ($refunds as $refund) {
                $entries->push([
                    'at' => $refund->created_at, 'module' => 'online',
                    'actor' => $refund->employee_id ? 'Empleado #'.$refund->employee_id : 'Personal',
                    'action' => 'Reembolso online de '.CatalogMoney::format($refund->amount_minor).' € ('.$refund->status.')',
                    'detail' => $refund->reason,
                    'order_id' => null, 'amount_minor' => $refund->amount_minor,
                ]);
            }
            $exports = ExportLog::query()
                ->where('restaurant_id', $restaurant->id)
                ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
                ->when($to, fn ($q) => $q->where('created_at', '<=', $to))
                ->latest('id')->limit($limit)->get();
            foreach ($exports as $export) {
                $entries->push([
                    'at' => $export->created_at, 'module' => 'online',
                    'actor' => $export->user_id ? 'Usuario #'.$export->user_id : 'Sistema',
                    'action' => 'Exportación '.$export->kind,
                    'detail' => ($export->period_from?->format('d/m/Y') ?? '').' - '.($export->period_to?->format('d/m/Y') ?? ''),
                    'order_id' => null, 'amount_minor' => null,
                ]);
            }
        }
        if (! $module || $module === 'promos') {
            $coupons = CouponRedemption::query()->with('coupon')
                ->where('restaurant_id', $restaurant->id)
                ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
                ->when($to, fn ($q) => $q->where('created_at', '<=', $to))
                ->latest('id')->limit($limit)->get();
            foreach ($coupons as $redemption) {
                $entries->push([
                    'at' => $redemption->created_at, 'module' => 'promos',
                    'actor' => 'Sistema',
                    'action' => 'Cupón '.$redemption->coupon?->code.' aplicado: -'.CatalogMoney::format($redemption->amount_minor).' €',
                    'detail' => null,
                    'order_id' => $redemption->order_id, 'amount_minor' => $redemption->amount_minor,
                ]);
            }
            $loyalty = LoyaltyEvent::query()
                ->where('restaurant_id', $restaurant->id)
                ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
                ->when($to, fn ($q) => $q->where('created_at', '<=', $to))
                ->when($employeeId, fn ($q) => $q->where('employee_id', $employeeId))
                ->latest('id')->limit($limit)->get();
            foreach ($loyalty as $event) {
                $entries->push([
                    'at' => $event->created_at, 'module' => 'promos',
                    'actor' => $event->employee_id ? 'Empleado #'.$event->employee_id : 'Sistema',
                    'action' => match ($event->kind) {
                        'accrue' => 'Fidelización: +'.$event->quantity.' sellos',
                        'redeem' => 'Fidelización: canje de recompensa',
                        default => 'Fidelización: '.$event->kind.' ('.$event->quantity.')',
                    },
                    'detail' => null,
                    'order_id' => $event->order_id, 'amount_minor' => null,
                ]);
            }
        }

        return $entries->sortByDesc(fn ($entry) => CarbonImmutable::parse($entry['at'])->timestamp)->values();
    }

    private function orderEventText($event): string
    {
        $data = $event->data ?? [];
        $table = $event->order?->table?->name ? ' en '.($event->order->table->name) : '';

        return match ($event->type) {
            'opened' => 'Abrió cuenta'.$table,
            'line_voided' => 'Anuló '.($data['quantity_before'] - $data['quantity_after'] ?? '?').' × línea #'.($data['line_id'] ?? '?').$table,
            'discount_applied' => 'Aplicó descuento de '.CatalogMoney::format($data['discount_minor'] ?? 0).' €'.$table,
            'manual_price_changed' => 'Cambió precio manual de '.CatalogMoney::format($data['previous_unit_minor'] ?? 0).' € a '.CatalogMoney::format($data['new_unit_minor'] ?? 0).' €'.$table,
            'table_transferred' => 'Trasladó cuenta de '.($data['from_table_name'] ?? '?').' a '.($data['to_table_name'] ?? '?'),
            'paid' => 'Cerró y cobró la cuenta'.$table,
            'cancelled' => 'Canceló cuenta vacía'.$table,
            'recovered' => 'Recuperó cuenta'.$table,
            default => str_replace('_', ' ', $event->type).$table,
        };
    }

    private function orderEventDetail($event): ?string
    {
        $data = $event->data ?? [];
        $parts = [];
        if (filled($data['reason'] ?? null)) {
            $parts[] = 'Motivo: '.$data['reason'];
        }
        if (isset($data['amount_minor'])) {
            $parts[] = 'Importe: '.CatalogMoney::format($data['amount_minor']).' €';
        }

        return $parts ? implode(' · ', $parts) : null;
    }
}
