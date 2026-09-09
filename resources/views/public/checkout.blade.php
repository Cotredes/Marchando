@extends('public.layout', ['title' => 'Confirmar pedido · '.$restaurant->name])

@section('content')
<a class="text-button" href="{{ $table ? route('public.qr.cart', $table->qr_token) : route('public.cart', [$restaurant, $channel]) }}">← Carrito</a>
<h1 class="mt-5 text-3xl font-semibold">Confirmar pedido</h1>

@if ($errors->any())
    <div class="mt-4 rounded-xl bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
@endif

<form method="POST" action="{{ $submitUrl ?? route('public.submit', [$restaurant, $channel]) }}" class="mt-6 space-y-4">
    @csrf
    <input type="hidden" name="request_key" value="{{ (string) str()->uuid() }}">
    @if ($channel !== 'dine_in')
        <input class="form-input" name="coupon_code" placeholder="Código promocional (opcional)" value="{{ old('coupon_code') }}">
    @endif
    <input class="form-input" name="name" placeholder="Nombre" value="{{ old('name') }}" required>
    <input class="form-input" name="phone" type="tel" placeholder="Teléfono" value="{{ old('phone') }}" required>
    @if ($channel === 'delivery')
        <textarea class="form-input min-h-24" name="address" placeholder="Dirección de entrega" required>{{ old('address') }}</textarea>
        <div class="grid grid-cols-2 gap-3">
            <input class="form-input" name="latitude" type="number" step="any" placeholder="Latitud (opcional)" value="{{ old('latitude') }}">
            <input class="form-input" name="longitude" type="number" step="any" placeholder="Longitud (opcional)" value="{{ old('longitude') }}">
        </div>
    @endif
    <input class="form-input" name="email" type="email" placeholder="Email opcional" value="{{ old('email') }}">
    <fieldset class="space-y-2">
        <legend class="font-semibold">Momento</legend>
        <label class="block rounded-xl border border-stone-200 p-3"><input type="radio" name="fulfillment_mode" value="asap" checked> Lo antes posible</label>
        <label class="block rounded-xl border border-stone-200 p-3"><input type="radio" name="fulfillment_mode" value="scheduled"> Elegir hora</label>
        <input class="form-input" name="requested_at" type="datetime-local" value="{{ old('requested_at') }}">
    </fieldset>
    <button class="button-primary w-full">Enviar pedido</button>
</form>
@endsection
