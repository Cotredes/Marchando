@extends('layouts.app', ['title' => 'Facturas', 'heading' => 'Facturas emitidas'])

@section('content')
@include('analytics.nav')
<p class="mb-4 text-sm text-stone-500">Documento fiscal preparatorio, sin valor VeriFactu/TicketBAI/SII. Las facturas son inmutables: ante un error se emitirá rectificativa en una misión posterior.</p>
@if ($invoices->isEmpty())
    <div class="rounded-2xl border border-dashed border-stone-300 bg-white p-10 text-center text-stone-500">Todavía no hay facturas.</div>
@else
    <div class="overflow-x-auto rounded-2xl border border-stone-200 bg-white">
        <table class="w-full min-w-[640px] text-sm">
            <thead><tr class="border-b border-stone-200 text-left text-xs uppercase tracking-wide text-stone-400"><th class="px-4 py-3">Número</th><th class="px-4 py-3">Fecha</th><th class="px-4 py-3">Cliente</th><th class="px-4 py-3">Venta</th><th class="px-4 py-3 text-right">Total</th></tr></thead>
            <tbody>@foreach ($invoices as $invoice)<tr class="border-t border-stone-100"><td class="px-4 py-3"><a class="font-semibold text-orange-700" href="{{ route('restaurant.invoices.show', [$restaurant, $invoice->id]) }}">{{ $invoice->reference }}</a></td><td class="px-4 py-3">{{ $invoice->issued_at?->format('d/m/Y H:i') }}</td><td class="px-4 py-3">{{ $invoice->customer?->fiscalName() ?? '—' }}</td><td class="px-4 py-3">#{{ $invoice->order_id }}</td><td class="px-4 py-3 text-right font-semibold">{{ \App\CatalogMoney::format($invoice->total_minor) }} €</td></tr>@endforeach</tbody>
        </table>
    </div>
    <div class="mt-4">{{ $invoices->links() }}</div>
@endif
@endsection
