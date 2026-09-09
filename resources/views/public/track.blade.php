@extends('public.layout', ['title' => 'Seguimiento · '.$request->restaurant->name])

@section('content')
<div class="text-center">
    <span class="mx-auto flex size-12 items-center justify-center rounded-2xl bg-orange-500 font-bold text-white">M</span>
    <p class="eyebrow mt-5">{{ $request->restaurant->name }}</p>
    <h1 class="mt-2 text-3xl font-semibold">Seguimiento del pedido</h1>
</div>

@if ($errors->any())
    <div class="mt-4 rounded-xl bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
@endif

<section id="public-status" data-public-tracking data-public-channel="public-order.{{ $request->public_token_hash }}" data-snapshot-url="{{ route('public.order.snapshot', request()->route('token')) }}" class="mt-8 rounded-3xl border border-stone-200 bg-white p-6 text-center" aria-live="polite">
    <p data-public-status-title class="text-xl font-semibold">{{ match($request->status) { 'pending' => 'Pedido recibido', 'accepted' => 'Pedido aceptado', 'rejected' => 'No hemos podido aceptar tu pedido', default => 'Estado del pedido' } }}</p>
    @if ($request->rejection_reason)
        <p class="mt-2 text-stone-500">{{ $request->rejection_reason }}</p>
    @endif
    @if (($intent?->status ?? null) === 'succeeded')
        <p class="mx-auto mt-3 w-fit rounded-full bg-emerald-100 px-4 py-1 text-sm font-semibold text-emerald-800">Pagado online</p>
    @elseif (($intent?->status ?? null) === 'failed')
        <p class="mx-auto mt-3 w-fit rounded-full bg-red-100 px-4 py-1 text-sm font-semibold text-red-800">El pago falló: puedes intentarlo de nuevo</p>
    @endif
    @if (($onlineAllowed ?? false) && ($intent?->status ?? null) !== 'succeeded')
        <form method="POST" action="{{ route('public.order.pay', ['token' => $request->public_token]) }}" class="mt-4">
            @csrf
            <button class="button-primary w-full">Pagar online {{ \App\CatalogMoney::format($request->total_minor) }} {{ $request->currency }}</button>
        </form>
    @endif
    <p class="mt-4 text-sm text-stone-500">Puedes cerrar esta página; tu pedido seguirá guardado.</p>
</section>
@endsection
