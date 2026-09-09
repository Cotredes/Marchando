@extends('public.layout', ['title' => 'Pago simulado · '.$request->restaurant->name])

@section('content')
<div class="mx-auto max-w-md text-center">
    <p class="eyebrow mt-8">Proveedor simulado (pruebas)</p>
    <h1 class="mt-2 text-3xl font-semibold">{{ \App\CatalogMoney::format($intent->amount_minor) }} {{ $intent->currency }}</h1>
    <p class="mt-2 text-stone-500">{{ $request->restaurant->name }} · pedido {{ $request->channel }}</p>
    <div class="mt-8 grid gap-3">
        <form method="POST" action="{{ route('public.order.fake.confirm', ['token' => $request->public_token]) }}">
            @csrf
            <input type="hidden" name="result" value="ok">
            <button class="button-primary w-full">Pagar (simulación)</button>
        </form>
        <form method="POST" action="{{ route('public.order.fake.confirm', ['token' => $request->public_token]) }}">
            @csrf
            <input type="hidden" name="result" value="fail">
            <button class="button-secondary w-full">Simular fallo</button>
        </form>
    </div>
    <p class="mt-4 text-sm text-stone-500">No se almacenan datos de tarjeta. Con Stripe real verías su página de pago.</p>
</div>
@endsection
