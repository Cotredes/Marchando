@extends('public.layout', ['title' => 'Carrito · '.$restaurant->name])

@section('content')
<a class="text-button" href="{{ $backUrl ?? route('public.catalog', [$restaurant, $channel]) }}">← Volver a la carta</a>
<h1 class="mt-5 text-3xl font-semibold">Tu carrito</h1>

@if ($errors->any())
    <div class="mt-4 rounded-xl bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
@endif

@if ($lines->isEmpty())
    <div class="mt-8 rounded-2xl border border-dashed border-stone-300 p-8 text-center text-stone-500">Todavía no has añadido productos.</div>
@else
    <div class="mt-6 space-y-3">
        @foreach ($lines as $line)
            <div class="rounded-2xl border border-stone-200 bg-white p-4">
                <div class="flex justify-between gap-4">
                    <div>
                        <p class="font-semibold">{{ $line['product']->name }}</p>
                        <p class="text-sm text-stone-500">{{ $line['price']['format_name'] }} · {{ $line['quantity'] }} ud.</p>
                    </div>
                    <strong>{{ \App\CatalogMoney::format($line['price']['total_minor'] * $line['quantity']) }} {{ $restaurant->currency }}</strong>
                </div>
                @if ($line['price']['adjustments'])
                    <p class="mt-2 text-sm text-stone-500">{{ collect($line['price']['adjustments'])->pluck('name')->join(', ') }}</p>
                @endif
            </div>
        @endforeach
    </div>
    <a class="button-primary mt-6 block text-center" href="{{ $table ? route('public.qr.checkout', $table->qr_token) : route('public.checkout', [$restaurant, $channel]) }}">Continuar</a>
@endif
@endsection
