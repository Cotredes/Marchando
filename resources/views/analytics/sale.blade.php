@extends('layouts.app', ['title' => 'Venta #'.$sale->id, 'heading' => 'Venta #'.$sale->id])

@section('content')
<a class="text-sm font-semibold text-orange-700" href="{{ route('restaurant.sales', $restaurant) }}">← Volver a ventas</a>
@if (session('status'))<div class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>@endif
@if ($errors->any())<div class="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>@endif

<div class="mt-4 grid gap-6 lg:grid-cols-3">
    <section class="rounded-2xl border border-stone-200 bg-white p-5 lg:col-span-2">
        <h2 class="font-semibold">Líneas (snapshot histórico)</h2>
        <table class="mt-3 w-full text-sm">
            <tbody>
                @foreach ($sale->lines as $line)
                    <tr class="border-t border-stone-100">
                        <td class="py-2">{{ $line->product_name }} @if ($line->format_name)<span class="text-stone-400">· {{ $line->format_name }}</span>@endif @if ($line->voided_quantity > 0)<span class="ml-1 rounded bg-stone-100 px-2 py-0.5 text-xs">anuladas {{ $line->voided_quantity }}</span>@endif</td>
                        <td class="py-2 text-right">{{ $line->activeQuantity() }} × {{ \App\CatalogMoney::format($line->unit_total_minor) }} €</td>
                        <td class="py-2 text-right font-semibold">{{ \App\CatalogMoney::format($line->active_line_total_minor) }} €</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div class="mt-4 space-y-1 border-t border-stone-200 pt-3 text-sm">
            <p class="flex justify-between"><span>Subtotal</span><span>{{ \App\CatalogMoney::format($totals['subtotal']) }} €</span></p>
            <p class="flex justify-between"><span>Descuento</span><span>-{{ \App\CatalogMoney::format($totals['discount']) }} €</span></p>
            <p class="flex justify-between"><span>Cargos</span><span>{{ \App\CatalogMoney::format($totals['charges']) }} €</span></p>
            <p class="flex justify-between text-base font-semibold"><span>Total</span><span>{{ \App\CatalogMoney::format($totals['total']) }} €</span></p>
        </div>
        <h3 class="mt-5 font-semibold">IVA (precios con impuesto incluido)</h3>
        <table class="mt-2 w-full text-sm"><tbody>@foreach ($totals['taxes'] as $tax)<tr class="border-t border-stone-100"><td class="py-2">{{ $tax['rate'] === null ? 'Sin IVA' : $tax['rate'].' %' }}</td><td class="py-2 text-right">Base {{ \App\CatalogMoney::format($tax['base_minor']) }} €</td><td class="py-2 text-right">IVA {{ \App\CatalogMoney::format($tax['tax_minor']) }} €</td></tr>@endforeach</tbody></table>
        <h3 class="mt-5 font-semibold">Pagos</h3>
        <ul class="mt-2 space-y-1 text-sm">@foreach ($sale->payments->where('status', 'succeeded') as $payment)<li>@foreach ($payment->tenders as $tender){{ $tender->method?->name }}: {{ \App\CatalogMoney::format($tender->amount_minor) }} € · @endforeach</li>@endforeach</ul>
    </section>
    <div class="space-y-6">
        <section class="rounded-2xl border border-stone-200 bg-white p-5 text-sm">
            <h2 class="font-semibold">Contexto</h2>
            <p class="mt-2">Canal: {{ $sale->channel }} · Origen: {{ $sale->origin }}</p>
            <p>Mesa: {{ $sale->table?->name ?? '—' }} · Comensales: {{ $sale->guest_count ?? '—' }}</p>
            <p>Cliente: {{ $sale->customer?->display_name ?? $sale->fulfillment?->customer_name ?? '—' }}</p>
            <p>Camarero: {{ $sale->currentEmployee?->display_name ?? '—' }}</p>
            <p>Cobrada: {{ $sale->paid_at?->setTimezone($restaurant->timezone)->format('d/m/Y H:i') }}</p>
        </section>
        <section class="rounded-2xl border border-stone-200 bg-white p-5 text-sm">
            <h2 class="font-semibold">Documentos</h2>
            @foreach ($sale->saleDocuments as $doc)<p class="mt-2">{{ $doc->kind === 'invoice' ? 'Factura' : 'Ticket' }} <strong>{{ $doc->reference }}</strong></p>@endforeach
            <a class="button-secondary mt-3 block text-center" href="{{ route('restaurant.sales.ticket', [$restaurant, $sale->id]) }}">Ver ticket</a>
        </section>
        @can('manageBilling', $restaurant)
        <section class="rounded-2xl border border-stone-200 bg-white p-5 text-sm">
            <h2 class="font-semibold">Crear factura</h2>
            <form method="POST" action="{{ route('restaurant.invoices.store', [$restaurant, $sale->id]) }}" class="mt-3 space-y-3">
                @csrf
                <div><label class="text-xs font-semibold text-stone-500">Cliente existente (ID)</label><input name="customer_id" type="number" min="1" class="form-input mt-1"></div>
                <p class="text-xs text-stone-500">O completa datos fiscales para un cliente nuevo:</p>
                <input name="display_name" placeholder="Nombre / razón social" class="form-input">
                <input name="tax_id" placeholder="NIF/CIF" class="form-input">
                <input name="legal_name" placeholder="Razón social (si empresa)" class="form-input">
                <input name="fiscal_address" placeholder="Dirección fiscal" class="form-input">
                <div class="grid grid-cols-2 gap-2"><input name="postal_code" placeholder="CP" class="form-input"><input name="city" placeholder="Localidad" class="form-input"></div>
                <button class="button-primary w-full">Emitir factura</button>
            </form>
        </section>
        @endcan
    </div>
</div>
@endsection
