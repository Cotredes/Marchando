@extends('layouts.app', ['title' => 'Analítica', 'heading' => 'Control del negocio'])

@section('content')
@include('analytics.nav')
<div class="mb-6 flex flex-wrap items-center gap-2">
    @foreach (['today' => 'Hoy', 'yesterday' => 'Ayer', '7d' => 'Últimos 7 días', 'month' => 'Este mes'] as $key => $label)
        <a href="{{ route('restaurant.analytics', [$restaurant, 'period' => $key]) }}" class="rounded-full border px-4 py-2 text-sm font-semibold {{ $period === $key ? 'border-orange-500 bg-orange-500 text-white' : 'border-stone-200 bg-white' }}">{{ $label }}</a>
    @endforeach
    <span class="ml-auto text-sm text-stone-500">{{ $from->setTimezone($restaurant->timezone)->format('d/m/Y') }} – {{ $to->setTimezone($restaurant->timezone)->format('d/m/Y') }}</span>
</div>

@if ($count === 0)
    <div class="rounded-2xl border border-dashed border-stone-300 bg-white p-10 text-center text-stone-500">Aún no hay ventas en este período.</div>
@else
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-2xl border border-stone-200 bg-white p-5"><p class="text-sm text-stone-500">Facturación (neta de {{ \App\CatalogMoney::format($refundedTotal) }} € reembolsados)</p><p class="mt-1 text-2xl font-semibold">{{ \App\CatalogMoney::format($netRevenue) }} €</p>@if ($previous)<p class="mt-1 text-sm {{ $previous['delta_pct'] >= 0 ? 'text-emerald-700' : 'text-red-700' }}">{{ $previous['delta_pct'] >= 0 ? '+' : '' }}{{ $previous['delta_pct'] }} % vs período anterior</p>@endif</div>
        <div class="rounded-2xl border border-stone-200 bg-white p-5"><p class="text-sm text-stone-500">Ventas</p><p class="mt-1 text-2xl font-semibold">{{ $count }}</p><p class="mt-1 text-sm text-stone-500">Ticket medio: {{ \App\CatalogMoney::format((int) round($revenue / max(1, $count))) }} €</p></div>
        <div class="rounded-2xl border border-stone-200 bg-white p-5"><p class="text-sm text-stone-500">Comensales</p><p class="mt-1 text-2xl font-semibold">{{ $guests }}</p><p class="mt-1 text-sm text-stone-500">@if ($guestCoverage < $count) Calculado sobre {{ $guestCoverage }} de {{ $count }} ventas con comensales registrados @else Ticket medio por comensal: {{ $guests > 0 ? \App\CatalogMoney::format((int) round($revenue / $guests)).' €' : '—' }} @endif</p></div>
        <div class="rounded-2xl border border-stone-200 bg-white p-5"><p class="text-sm text-stone-500">Descuentos / Anulaciones</p><p class="mt-1 text-2xl font-semibold">-{{ \App\CatalogMoney::format((int) ($discountAgg->total ?? 0)) }} €</p><p class="mt-1 text-sm text-stone-500">{{ $discountAgg->times ?? 0 }} descuentos · {{ \App\CatalogMoney::format($voids) }} € anulados (no suman a ventas)</p></div>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <section class="rounded-2xl border border-stone-200 bg-white p-5"><h2 class="font-semibold">Ventas por canal</h2><table class="mt-3 w-full text-sm"><tbody>@foreach ($byChannel as $row)<tr class="border-t border-stone-100"><td class="py-2">{{ $row->channel === 'dine_in' ? 'Local' : ($row->channel === 'takeaway' ? 'Take Away' : 'Delivery') }} <span class="text-stone-400">· {{ $row->origin }}</span></td><td class="py-2 text-right">{{ $row->sales }}</td><td class="py-2 text-right font-semibold">{{ \App\CatalogMoney::format($row->revenue) }} €</td></tr>@endforeach</tbody></table></section>
        <section class="rounded-2xl border border-stone-200 bg-white p-5"><h2 class="font-semibold">Métodos de pago</h2><table class="mt-3 w-full text-sm"><tbody>@foreach ($tenders as $row)<tr class="border-t border-stone-100"><td class="py-2">{{ $row->method }}</td><td class="py-2 text-right font-semibold">{{ \App\CatalogMoney::format($row->total) }} €</td></tr>@endforeach</tbody></table><p class="mt-2 text-xs text-stone-500">Efectivo: {{ \App\CatalogMoney::format($cashTotal) }} € · Tarjeta/otros: {{ \App\CatalogMoney::format($cardTotal) }} €. Los pagos mixtos reparten cada parte en su método sin duplicar la venta.</p></section>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <section class="rounded-2xl border border-stone-200 bg-white p-5"><h2 class="font-semibold">Más vendidos (unidades)</h2><ol class="mt-3 space-y-2 text-sm">@foreach ($productsQty->take(10) as $row)<li class="flex justify-between gap-3 border-t border-stone-100 py-2"><span>{{ $row['name'] }}</span><strong>{{ $row['qty'] }}</strong></li>@endforeach</ol></section>
        <section class="rounded-2xl border border-stone-200 bg-white p-5"><h2 class="font-semibold">Más facturación</h2><ol class="mt-3 space-y-2 text-sm">@foreach ($productsRevenue->take(10) as $row)<li class="flex justify-between gap-3 border-t border-stone-100 py-2"><span>{{ $row['name'] }} <span class="text-stone-400">· {{ $row['qty'] }} ud.</span></span><strong>{{ \App\CatalogMoney::format($row['revenue']) }} €</strong></li>@endforeach</ol></section>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <section class="rounded-2xl border border-stone-200 bg-white p-5"><h2 class="font-semibold">Por categoría</h2><table class="mt-3 w-full text-sm"><tbody>@foreach ($categories as $row)<tr class="border-t border-stone-100"><td class="py-2">{{ $row['name'] }}</td><td class="py-2 text-right">{{ $row['qty'] }} ud.</td><td class="py-2 text-right font-semibold">{{ \App\CatalogMoney::format($row['revenue']) }} €</td></tr>@endforeach</tbody></table></section>
        <section class="rounded-2xl border border-stone-200 bg-white p-5"><h2 class="font-semibold">Por empleado (responsable de cuenta)</h2><table class="mt-3 w-full text-sm"><tbody>@foreach ($byEmployee as $row)<tr class="border-t border-stone-100"><td class="py-2">{{ $row->name }}</td><td class="py-2 text-right">{{ $row->sales }}</td><td class="py-2 text-right font-semibold">{{ \App\CatalogMoney::format($row->revenue) }} €</td></tr>@endforeach</tbody></table><p class="mt-2 text-xs text-stone-500">Métrica operativa y de auditoría, no un ranking laboral.</p></section>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <section class="rounded-2xl border border-stone-200 bg-white p-5"><h2 class="font-semibold">Margen bruto estimado (solo coste de producto)</h2>@if ($revenue > 0 && $costKnownRevenue < $revenue)<p class="mt-2 rounded-xl bg-amber-50 px-3 py-2 text-sm text-amber-800">Coste configurado en el {{ round($costKnownRevenue / $revenue * 100) }} % de las ventas analizadas. No es beneficio neto: faltan personal, alquiler, energía e impuestos generales.</p>@endif<p class="mt-3 text-sm">Ingresos: <strong>{{ \App\CatalogMoney::format($revenue) }} €</strong> · Coste estimado: <strong>{{ \App\CatalogMoney::format($costTotal) }} €</strong> · Margen: <strong>{{ \App\CatalogMoney::format($revenue - $costTotal) }} €</strong></p></section>
        <section class="rounded-2xl border border-stone-200 bg-white p-5"><h2 class="font-semibold">Cocina (Misión 9)</h2><p class="mt-3 text-sm">{{ $kitchen['items'] }} líneas · inicio medio: {{ $kitchen['avg_to_start'] !== null ? gmdate('i:s', $kitchen['avg_to_start']) : '—' }} · listo medio: {{ $kitchen['avg_to_ready'] !== null ? gmdate('i:s', $kitchen['avg_to_ready']) : '—' }}</p><p class="mt-2 text-sm text-stone-500">Delivery: {{ \App\CatalogMoney::format($deliveryFees) }} € de reparto cobrado en el período.</p></section>
    </div>

    <div class="mt-6 rounded-2xl border border-stone-200 bg-white p-5"><h2 class="font-semibold">Ventas por día</h2><div class="mt-3 flex items-end gap-1 overflow-x-auto" style="min-height: 96px">@php($max = max(1, $byDay->max('revenue') ?? 1)) @foreach ($byDay as $row)<div class="flex w-10 shrink-0 flex-col items-center gap-1" title="{{ $row->day }}: {{ \App\CatalogMoney::format($row->revenue) }} €"><div class="w-6 rounded-t bg-orange-500" style="height: {{ max(4, round($row->revenue / $max * 80)) }}px"></div><span class="text-[10px] text-stone-500">{{ substr($row->day, 8, 2) }}</span></div>@endforeach</div></div>

    <div class="mt-6 flex flex-wrap gap-2">
        <a class="button-secondary" href="{{ route('restaurant.exports.sales', [$restaurant, 'period' => $period]) }}">Exportar ventas (CSV)</a>
        <a class="button-secondary" href="{{ route('restaurant.exports.products', [$restaurant, 'period' => $period]) }}">Exportar productos (CSV)</a>
        <a class="button-secondary" href="{{ route('restaurant.exports.payments', [$restaurant, 'period' => $period]) }}">Exportar pagos (CSV)</a>
    </div>
@endif
@endsection
