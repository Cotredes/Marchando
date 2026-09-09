@extends('layouts.app', ['title' => 'Ventas', 'heading' => 'Histórico de ventas'])

@section('content')
@include('analytics.nav')
<form method="GET" class="mb-5 grid gap-3 rounded-2xl border border-stone-200 bg-white p-4 sm:grid-cols-3 lg:grid-cols-6">
    <div><label class="text-xs font-semibold text-stone-500">Desde</label><input type="date" name="from" value="{{ $from->setTimezone($restaurant->timezone)->format('Y-m-d') }}" class="form-input mt-1"></div>
    <div><label class="text-xs font-semibold text-stone-500">Hasta</label><input type="date" name="to" value="{{ $to->setTimezone($restaurant->timezone)->format('Y-m-d') }}" class="form-input mt-1"></div>
    <div><label class="text-xs font-semibold text-stone-500">Canal</label><select name="channel" class="form-input mt-1"><option value="">Todos</option>@foreach (['dine_in' => 'Local', 'takeaway' => 'Take Away', 'delivery' => 'Delivery'] as $key => $label)<option value="{{ $key }}" @selected(($filters['channel'] ?? '') === $key)>{{ $label }}</option>@endforeach</select></div>
    <div><label class="text-xs font-semibold text-stone-500">Camarero</label><select name="employee_id" class="form-input mt-1"><option value="">Todos</option>@foreach ($employees as $employee)<option value="{{ $employee->id }}" @selected(($filters['employee_id'] ?? '') == $employee->id)>{{ $employee->display_name }}</option>@endforeach</select></div>
    <div><label class="text-xs font-semibold text-stone-500">Pago</label><select name="method" class="form-input mt-1"><option value="">Todos</option><option value="cash" @selected(($filters['method'] ?? '') === 'cash')>Efectivo</option><option value="card" @selected(($filters['method'] ?? '') === 'card')>Tarjeta/otros</option></select></div>
    <div><label class="text-xs font-semibold text-stone-500">Buscar</label><input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Mesa, cliente, nº" class="form-input mt-1"></div>
    <div class="sm:col-span-3 lg:col-span-6"><button class="button-primary">Filtrar</button></div>
</form>

@if ($sales->isEmpty())
    <div class="rounded-2xl border border-dashed border-stone-300 bg-white p-10 text-center text-stone-500">Aún no hay ventas en este período.</div>
@else
    <div class="overflow-x-auto rounded-2xl border border-stone-200 bg-white">
        <table class="w-full min-w-[720px] text-sm">
            <thead><tr class="border-b border-stone-200 text-left text-xs uppercase tracking-wide text-stone-400"><th class="px-4 py-3">Venta</th><th class="px-4 py-3">Fecha</th><th class="px-4 py-3">Canal</th><th class="px-4 py-3">Mesa</th><th class="px-4 py-3">Cliente</th><th class="px-4 py-3">Camarero</th><th class="px-4 py-3 text-right">Total</th><th class="px-4 py-3">Docs</th></tr></thead>
            <tbody>
                @foreach ($sales as $sale)
                    <tr class="border-t border-stone-100">
                        <td class="px-4 py-3"><a class="font-semibold text-orange-700" href="{{ route('restaurant.sales.show', [$restaurant, $sale->id]) }}">#{{ $sale->id }}</a></td>
                        <td class="px-4 py-3">{{ $sale->paid_at?->setTimezone($restaurant->timezone)->format('d/m H:i') }}</td>
                        <td class="px-4 py-3">{{ $sale->channel === 'dine_in' ? 'Local' : ($sale->channel === 'takeaway' ? 'Take Away' : 'Delivery') }}@if ($sale->origin === 'qr') <span class="text-xs text-stone-400">· QR</span>@endif</td>
                        <td class="px-4 py-3">{{ $sale->table?->name ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $sale->customer?->display_name ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $sale->currentEmployee?->display_name ?? '—' }}</td>
                        <td class="px-4 py-3 text-right font-semibold">{{ \App\CatalogMoney::format($sale->total_minor) }} €</td>
                        <td class="px-4 py-3 text-xs">{{ $sale->saleDocuments->isNotEmpty() ? $sale->saleDocuments->pluck('reference')->join(', ') : '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $sales->links() }}</div>
@endif
@endsection
