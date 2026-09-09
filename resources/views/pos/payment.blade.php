@extends('layouts.pos', ['title' => 'Cobrar'])
@section('content')
@php
    $target = $part ?? null;
    $targetTotal = $target?->total_minor ?? $order->total_minor;
    $paid = $target ? $target->payments()->where('status', 'succeeded')->sum('amount_minor') : $order->payments()->where('status', 'succeeded')->sum('amount_minor');
    $remaining = max(0, $targetTotal - $paid);
@endphp
<div class="mx-auto max-w-3xl">
    <a class="text-button" href="{{ route('restaurant.pos.orders.show', [$restaurant, $order]) }}">← Volver a la cuenta</a>
    <div class="mt-5 rounded-2xl border border-stone-200 bg-white p-6">
        <p class="eyebrow">{{ $order->table->name }} · {{ $target?->label ?? 'Cuenta completa' }}</p>
        <h1 class="mt-1 text-3xl font-semibold">Cobrar</h1>
        <div class="mt-6 grid gap-3 sm:grid-cols-3">
            <div><p class="text-sm text-stone-500">Total</p><p class="text-xl font-semibold">{{ \App\CatalogMoney::format($targetTotal) }} €</p></div>
            <div><p class="text-sm text-stone-500">Pagado</p><p class="text-xl font-semibold">{{ \App\CatalogMoney::format($paid) }} €</p></div>
            <div><p class="text-sm text-stone-500">Pendiente</p><p class="text-xl font-semibold text-orange-700">{{ \App\CatalogMoney::format($remaining) }} €</p></div>
        </div>
        @if(!$session)
            <div class="mt-6 rounded-xl bg-orange-50 p-4 text-sm text-orange-800">No hay una sesión de caja abierta. Ábrela antes de registrar un cobro.</div>
        @endif
        @if($errors->any())<div class="mt-5 rounded-xl bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>@endif
        <form method="POST" action="{{ $target ? route('restaurant.pos.split-parts.payment.store', [$restaurant, $target]) : route('restaurant.pos.orders.payment.store', [$restaurant, $order]) }}" class="mt-6 space-y-5">
            @csrf
            <input type="hidden" name="cash_session_id" value="{{ $session?->id }}">
            <input type="hidden" name="request_key" value="{{ (string) str()->uuid() }}">
            <input type="hidden" name="tenders[0][method_id]" value="{{ $methods->first()?->id }}">
            <div class="space-y-3">
                @foreach($methods as $method)
                    <label class="flex items-center gap-3 rounded-xl border border-stone-200 p-4">
                        <input type="radio" name="selected_method" value="{{ $method->id }}" @checked($loop->first) onchange="document.querySelector('[name=\'tenders[0][method_id]\']').value=this.value">
                        <span class="flex-1 font-semibold">{{ $method->name }}</span>
                        <input class="form-input w-36" type="number" min="0" step="1" name="tenders[0][amount_minor]" value="{{ $remaining }}" required>
                        @if($method->is_cash)<input class="form-input w-36" type="number" min="0" step="1" name="tenders[0][tendered_minor]" value="{{ $remaining }}" placeholder="Entregado">@endif
                    </label>
                @endforeach
            </div>
            <p class="text-xs text-stone-500">Los importes se expresan en céntimos. El servidor valida el total y calcula el cambio.</p>
            <button class="button-primary w-full" @disabled(!$session || $remaining < 1)>Registrar {{ \App\CatalogMoney::format($remaining) }} €</button>
        </form>
    </div>
</div>
@endsection
