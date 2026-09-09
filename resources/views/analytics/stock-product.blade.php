@extends('layouts.app', ['title' => $product->name, 'heading' => $product->name])

@section('content')
<a class="text-sm font-semibold text-orange-700" href="{{ route('restaurant.stock', $restaurant) }}">← Volver a stock</a>
@if (session('status'))<div class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>@endif
@if ($errors->any())<div class="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>@endif
<p class="mt-2 text-sm text-stone-500">Stock actual: <strong>{{ $product->track_stock ? $product->stock_quantity : 'sin control' }}</strong> · Mínimo: {{ $product->stock_minimum ?? '—' }}. Cada cambio queda explicado por un movimiento; nunca se edita la cifra a mano.</p>
<div class="mt-4 grid gap-6 lg:grid-cols-3">
    <section class="rounded-2xl border border-stone-200 bg-white p-5 lg:col-span-2">
        <h2 class="font-semibold">Historial de movimientos</h2>
        <form method="GET" class="mt-3 flex gap-2"><select name="type" class="form-input w-48"><option value="">Todos los tipos</option>@foreach (['initial' => 'Inicial', 'entry' => 'Entradas', 'sale' => 'Ventas', 'sale_reversal' => 'Devoluciones', 'adjust_in' => 'Ajustes +', 'adjust_out' => 'Ajustes −'] as $key => $label)<option value="{{ $key }}" @selected(request('type') === $key)>{{ $label }}</option>@endforeach</select><button class="button-secondary">Filtrar</button></form>
        <div class="mt-3 space-y-2 text-sm">@forelse ($movements as $movement)<div class="rounded-xl border border-stone-100 p-3"><p><strong>{{ $movement->quantity_delta > 0 ? '+' : '' }}{{ $movement->quantity_delta }}</strong> · {{ $movement->type }} · stock resultante {{ $movement->resulting_quantity }}</p><p class="text-stone-500">{{ $movement->created_at?->format('d/m/Y H:i') }} · {{ $movement->employee?->display_name ?? 'Sistema' }}@if ($movement->reason) · {{ $movement->reason }}@endif</p></div>@empty<p class="text-stone-500">Sin movimientos.</p>@endforelse</div>
        <div class="mt-4">{{ $movements->links() }}</div>
    </section>
    <div class="space-y-6">
        @can('manageStock', $restaurant)
        <section class="rounded-2xl border border-stone-200 bg-white p-5">
            <h2 class="font-semibold">Control de stock</h2>
            <form method="POST" action="{{ route('restaurant.stock.settings', [$restaurant, $product->id]) }}" class="mt-3 space-y-3">
                @csrf @method('PATCH')
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="track_stock" value="1" @checked($product->track_stock)> Gestionar stock</label>
                <div><label class="text-xs font-semibold text-stone-500">Cantidad actual</label><input name="stock_quantity" type="number" min="0" max="1000000" value="{{ $product->stock_quantity }}" class="form-input mt-1"></div>
                <div><label class="text-xs font-semibold text-stone-500">Mínimo (opcional)</label><input name="stock_minimum" type="number" min="0" max="1000000" value="{{ $product->stock_minimum }}" class="form-input mt-1"></div>
                <button class="button-primary w-full">Guardar</button>
            </form>
        </section>
        <section class="rounded-2xl border border-stone-200 bg-white p-5">
            <h2 class="font-semibold">Añadir stock</h2>
            <form method="POST" action="{{ route('restaurant.stock.entries', [$restaurant, $product->id]) }}" class="mt-3 space-y-3">
                @csrf
                <input name="quantity" type="number" min="1" max="1000000" placeholder="Cantidad" class="form-input">
                <input name="reason" placeholder="Motivo (p. ej. Reposición)" class="form-input">
                <button class="button-primary w-full">Registrar entrada</button>
            </form>
        </section>
        <section class="rounded-2xl border border-stone-200 bg-white p-5">
            <h2 class="font-semibold">Ajustar a conteo físico</h2>
            <form method="POST" action="{{ route('restaurant.stock.adjust', [$restaurant, $product->id]) }}" class="mt-3 space-y-3">
                @csrf
                <input name="stock_quantity" type="number" min="0" max="1000000" placeholder="Cantidad contada" class="form-input">
                <input name="reason" placeholder="Motivo obligatorio" class="form-input">
                <button class="button-secondary w-full">Ajustar</button>
            </form>
        </section>
        @endcan
    </div>
</div>
@endsection
