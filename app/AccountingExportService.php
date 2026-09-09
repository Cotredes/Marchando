<?php

namespace App;

use App\Models\ExportLog;
use App\Models\PaymentReversal;
use App\Models\PaymentTender;
use App\Models\User;
use Carbon\CarbonImmutable;

class AccountingExportService
{
    public function mapping($restaurant)
    {
        return $restaurant->accountingMapping()->firstOrCreate(['restaurant_id' => $restaurant->id]);
    }

    /** @return array{filename:string,head:array<int,string>,rows:array<int,array<int,mixed>>} */
    public function sales($restaurant, CarbonImmutable $from, CarbonImmutable $to, ?User $user = null): array
    {
        $docs = $restaurant->saleDocuments()->with(['order.table', 'customer'])->whereBetween('issued_at', [$from->toDateTimeString(), $to->toDateTimeString()])->orderBy('issued_at')->get();
        $rows = [];
        foreach ($docs as $doc) {
            foreach ($doc->tax_breakdown ?? [] as $tax) {
                $rows[] = [$doc->issued_at?->format('d/m/Y'), $doc->reference, $doc->kind === 'invoice' ? 'Factura' : 'Ticket', $doc->order?->channel ?? '', $doc->customer?->fiscalName() ?? '', $this->money($tax['base_minor'] ?? 0), $tax['rate'] ?? '0', $this->money($tax['tax_minor'] ?? 0), $this->money($doc->total_minor)];
            }
            if ($docs && ($doc->tax_breakdown ?? []) === []) {
                $rows[] = [$doc->issued_at?->format('d/m/Y'), $doc->reference, $doc->kind, '', '', $this->money($doc->total_minor), '', $this->money(0), $this->money($doc->total_minor)];
            }
        }
        $this->log($restaurant, $user, 'ventas', $from, $to);

        return ['filename' => 'ventas.csv', 'head' => ['fecha', 'documento', 'tipo', 'canal', 'cliente', 'base', 'tipo_iva', 'cuota', 'total'], 'rows' => $rows];
    }

    /** @return array{filename:string,head:array<int,string>,rows:array<int,array<int,mixed>>} */
    public function invoices($restaurant, CarbonImmutable $from, CarbonImmutable $to, ?User $user = null): array
    {
        $docs = $restaurant->saleDocuments()->with('customer')->where('kind', 'invoice')->whereBetween('issued_at', [$from->toDateTimeString(), $to->toDateTimeString()])->orderBy('issued_at')->get();
        $rows = $docs->map(fn ($doc) => [$doc->issued_at?->format('d/m/Y'), $doc->reference, $doc->customer?->fiscalName() ?? '', $doc->customer?->tax_id ?? '', $this->money($doc->subtotal_minor - $doc->discount_minor), $this->money($doc->charges_minor), $this->money($doc->total_minor), $doc->order_id])->all();
        $this->log($restaurant, $user, 'facturas', $from, $to);

        return ['filename' => 'facturas.csv', 'head' => ['fecha', 'numero', 'cliente', 'nif', 'base_neta', 'cargos', 'total', 'venta'], 'rows' => $rows];
    }

    /** @return array{filename:string,head:array<int,string>,rows:array<int,array<int,mixed>>} */
    public function payments($restaurant, CarbonImmutable $from, CarbonImmutable $to, ?User $user = null): array
    {
        $tenders = PaymentTender::query()->join('payments', 'payments.id', '=', 'payment_tenders.payment_id')->join('payment_methods', 'payment_methods.id', '=', 'payment_tenders.payment_method_id')
            ->where('payments.restaurant_id', $restaurant->id)->where('payments.status', 'succeeded')
            ->whereBetween('payments.succeeded_at', [$from->toDateTimeString(), $to->toDateTimeString()])
            ->select(['payments.succeeded_at', 'payments.order_id', 'payment_methods.name as method', 'payment_methods.is_cash', 'payment_tenders.amount_minor'])->orderBy('payments.succeeded_at')->get();
        $rows = $tenders->map(fn ($tender) => [$tender->succeeded_at, $tender->order_id, $tender->method, $tender->is_cash ? 'efectivo' : 'digital', $this->money($tender->amount_minor)])->all();
        $refunds = $restaurant->onlineIntents()->with('refunds')->get()->flatMap->refunds->where('status', 'succeeded')->filter(fn ($refund) => $refund->created_at->between($from, $to));
        foreach ($refunds as $refund) {
            $rows[] = [$refund->created_at->format('Y-m-d H:i:s'), $refund->intent?->order_id ?? '', 'reembolso online', 'digital', $this->money(-$refund->amount_minor)];
        }
        $reversals = PaymentReversal::query()->where('restaurant_id', $restaurant->id)->whereBetween('created_at', [$from->toDateTimeString(), $to->toDateTimeString()])->get();
        foreach ($reversals as $reversal) {
            $rows[] = [$reversal->created_at->format('Y-m-d H:i:s'), $reversal->payment?->order_id ?? '', 'devolución TPV', 'caja', $this->money(-$reversal->amount_minor)];
        }
        $this->log($restaurant, $user, 'pagos', $from, $to);

        return ['filename' => 'pagos.csv', 'head' => ['fecha', 'venta', 'metodo', 'tipo', 'importe'], 'rows' => $rows];
    }

    /** @return array{filename:string,head:array<int,string>,rows:array<int,array<int,mixed>>} */
    public function cash($restaurant, CarbonImmutable $from, CarbonImmutable $to, ?User $user = null): array
    {
        $sessions = $restaurant->cashSessions()->with('register')->whereBetween('opened_at', [$from->toDateTimeString(), $to->toDateTimeString()])->orderBy('opened_at')->get();
        $rows = [];
        foreach ($sessions as $session) {
            $rows[] = [$session->opened_at?->format('d/m/Y H:i'), $session->register?->name ?? '', 'apertura', $this->money($session->opening_float_minor)];
            foreach ($session->movements as $movement) {
                $rows[] = [$movement->created_at?->format('d/m/Y H:i'), $session->register?->name ?? '', $movement->type.($movement->reason ? ' · '.$movement->reason : ''), $this->money($movement->amount_minor)];
            }
            if ($session->status === 'closed') {
                $rows[] = [$session->closed_at?->format('d/m/Y H:i'), $session->register?->name ?? '', 'arqueo esperado', $this->money($session->expected_cash_minor ?? 0)];
                $rows[] = [$session->closed_at?->format('d/m/Y H:i'), $session->register?->name ?? '', 'arqueo declarado', $this->money($session->declared_cash_minor ?? 0)];
                $rows[] = [$session->closed_at?->format('d/m/Y H:i'), $session->register?->name ?? '', 'diferencia', $this->money($session->difference_minor ?? 0)];
            }
        }
        $this->log($restaurant, $user, 'caja', $from, $to);

        return ['filename' => 'caja.csv', 'head' => ['fecha', 'caja', 'concepto', 'importe'], 'rows' => $rows];
    }

    private function money(int $minor): string
    {
        return number_format($minor / 100, 2, '.', '');
    }

    private function log($restaurant, ?User $user, string $kind, CarbonImmutable $from, CarbonImmutable $to): void
    {
        ExportLog::create(['restaurant_id' => $restaurant->id, 'user_id' => $user?->id, 'kind' => $kind, 'period_from' => $from->toDateString(), 'period_to' => $to->toDateString()]);
    }
}
