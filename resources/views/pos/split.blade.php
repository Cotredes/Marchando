@extends('layouts.pos', ['title' => 'Dividir cuenta'])
@section('content')
<div class="mx-auto max-w-4xl">
    <a class="text-button" href="{{ route('restaurant.pos.orders.show', [$restaurant, $order]) }}">← Volver a la cuenta</a>
    <div class="mt-5"><p class="eyebrow">Split Bill · {{ $order->table->name }}</p><h1 class="mt-1 text-3xl font-semibold">Preparar subcuentas</h1><p class="mt-2 text-stone-500">Total actual: <strong>{{ \App\CatalogMoney::format($order->total_minor) }} €</strong>.</p></div>
    @if(session('status'))<div class="mt-5 rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="mt-5 rounded-xl bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>@endif
    @if(!$activePlan)
        <div class="mt-8 grid gap-6 md:grid-cols-2">
            <section class="rounded-2xl border border-stone-200 bg-white p-6"><h2 class="text-xl font-semibold">Partes iguales</h2><form method="POST" action="{{ route('restaurant.pos.orders.split.equal', [$restaurant, $order]) }}" class="mt-5 flex gap-3">@csrf<input class="form-input" type="number" name="parts" min="2" max="20" value="2" required><button class="button-primary">Preparar</button></form></section>
            <section class="rounded-2xl border border-stone-200 bg-white p-6"><h2 class="text-xl font-semibold">Por productos</h2><form method="POST" action="{{ route('restaurant.pos.orders.split.products', [$restaurant, $order]) }}" class="mt-5 space-y-3">@csrf @foreach($order->lines as $line)<div class="flex items-center justify-between gap-3 rounded-xl bg-stone-50 p-3"><span class="text-sm font-semibold">{{ $line->product_name }} <span class="font-normal text-stone-500">({{ $line->activeQuantity() }} disponibles)</span></span><input class="form-input w-24" type="number" name="allocations[0][{{ $line->id }}]" min="0" max="{{ $line->activeQuantity() }}" value="0"></div>@endforeach<button class="button-primary w-full">Crear subcuentas</button></form></section>
        </div>
    @else
        <div class="mt-8 rounded-2xl border border-stone-200 bg-white p-6"><h2 class="text-xl font-semibold">Subcuentas cobrables</h2><div class="mt-4 grid gap-3 sm:grid-cols-3">@foreach($activePlan->parts as $part)<div class="rounded-xl bg-stone-50 p-4"><p class="font-semibold">{{ $part->label }}</p><p class="mt-2 text-lg">{{ \App\CatalogMoney::format($part->total_minor) }} €</p><p class="text-xs text-stone-500">{{ $part->status === 'paid' ? 'Cobrada' : 'Pendiente de cobro' }}</p>@if($part->status !== 'paid')<a class="button-primary mt-3 inline-block" href="{{ route('restaurant.pos.split-parts.payment', [$restaurant, $part]) }}">Cobrar</a>@endif</div>@endforeach</div><form method="POST" action="{{ route('restaurant.pos.splits.cancel', [$restaurant, $activePlan]) }}" class="mt-5">@csrf<button class="button-secondary">Cancelar preparación</button></form></div>
    @endif
</div>
@endsection
