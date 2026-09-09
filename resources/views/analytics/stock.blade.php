@extends('layouts.app', ['title' => 'Stock', 'heading' => 'Stock operativo'])

@section('content')
@include('analytics.nav')
<form method="GET" class="mb-5 flex flex-wrap gap-2">
    <input name="q" value="{{ request('q') }}" placeholder="Buscar producto" class="form-input w-56">
    <select name="status" class="form-input w-44"><option value="all" @selected($status === 'all')>Todos</option><option value="tracked" @selected($status === 'tracked')>Controlados</option><option value="low" @selected($status === 'low')>Stock bajo</option><option value="out" @selected($status === 'out')>Agotados</option></select>
    <button class="button-secondary">Filtrar</button>
</form>
<div class="overflow-x-auto rounded-2xl border border-stone-200 bg-white">
    <table class="w-full min-w-[680px] text-sm">
        <thead><tr class="border-b border-stone-200 text-left text-xs uppercase tracking-wide text-stone-400"><th class="px-4 py-3">Producto</th><th class="px-4 py-3">Categoría</th><th class="px-4 py-3 text-right">Stock</th><th class="px-4 py-3 text-right">Mínimo</th><th class="px-4 py-3">Estado</th></tr></thead>
        <tbody>
            @foreach ($products as $product)
                <tr class="border-t border-stone-100">
                    <td class="px-4 py-3"><a class="font-semibold text-orange-700" href="{{ route('restaurant.stock.show', [$restaurant, $product->id]) }}">{{ $product->name }}</a></td>
                    <td class="px-4 py-3">{{ $product->category?->name ?? '—' }}</td>
                    <td class="px-4 py-3 text-right">{{ $product->track_stock ? $product->stock_quantity : '—' }}</td>
                    <td class="px-4 py-3 text-right">{{ $product->stock_minimum ?? '—' }}</td>
                    <td class="px-4 py-3">@if (! $product->track_stock)<span class="text-stone-400">Sin control</span>@elseif ($product->isOutOfStock())<span class="rounded-full bg-red-100 px-2 py-1 text-xs font-semibold text-red-800">Agotado</span>@elseif ($product->isLowStock())<span class="rounded-full bg-amber-100 px-2 py-1 text-xs font-semibold text-amber-800">Stock bajo</span>@else<span class="rounded-full bg-emerald-100 px-2 py-1 text-xs font-semibold text-emerald-800">OK</span>@endif</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $products->links() }}</div>
@endsection
