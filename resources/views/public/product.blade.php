@extends('public.layout', ['title' => $product->name.' · '.$restaurant->name])

@section('content')
<a class="text-button" href="{{ $backUrl ?? route('public.catalog', [$restaurant, $channel]) }}">← Volver a la carta</a>

<article class="mt-5 overflow-hidden rounded-3xl border border-stone-200 bg-white">
    @if ($product->image_path)
        <img class="h-56 w-full object-cover" src="{{ asset('storage/'.$product->image_path) }}" alt="{{ $product->name }}">
    @endif
    <div class="p-6">
        <h1 class="text-3xl font-semibold">{{ $product->name }}</h1>
        @if ($product->description)
            <p class="mt-2 text-stone-600">{{ $product->description }}</p>
        @endif
        @if ($product->allergens->isNotEmpty())
            <p class="mt-4 rounded-xl bg-orange-50 px-4 py-3 text-sm text-orange-900"><strong>Alérgenos declarados:</strong> {{ $product->allergens->pluck('name')->join(', ') }}</p>
        @endif
        @if ($errors->any())
            <div class="mt-4 rounded-xl bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
        @endif
        <form method="POST" action="{{ str_starts_with($context, 'public_cart_') && $channel === 'dine_in' ? route('public.qr.add', request()->route('token')) : route('public.add', [$restaurant, $channel]) }}" class="mt-6 space-y-5">
            @csrf
            <input type="hidden" name="product_id" value="{{ $product->id }}">
            <div>
                <label class="form-label" for="quantity">Cantidad</label>
                <input class="form-input mt-1 w-28" id="quantity" name="quantity" type="number" min="1" max="20" value="1" required>
            </div>
            @if ($product->formats->where('is_active', true)->isNotEmpty())
                <fieldset>
                    <legend class="font-semibold">Elige un formato</legend>
                    <div class="mt-2 space-y-2">
                        @foreach ($product->formats->where('is_active', true) as $format)
                            <label class="flex items-center justify-between rounded-xl border border-stone-200 p-3">
                                <span><input type="radio" name="format_id" value="{{ $format->id }}" @checked($format->is_default)> {{ $format->name }}</span>
                                <strong>{{ \App\CatalogMoney::format($format->price_minor) }} {{ $restaurant->currency }}</strong>
                            </label>
                        @endforeach
                    </div>
                </fieldset>
            @endif
            @foreach ($product->modifierGroupAssignments as $assignment)
                @if ($assignment->group->is_active)
                    <fieldset>
                        <legend class="font-semibold">{{ $assignment->group->name }} <span class="text-sm font-normal text-stone-500">{{ $assignment->group->min_selections > 0 ? 'Obligatorio' : 'Opcional' }}</span></legend>
                        <div class="mt-2 space-y-2">
                            @foreach ($assignment->group->options->where('is_active', true) as $option)
                                <label class="flex items-center justify-between rounded-xl border border-stone-200 p-3">
                                    <span><input type="checkbox" name="selections[{{ $assignment->id }}][{{ $option->id }}]" value="1"> {{ $option->name }}</span>
                                    @if ($option->price_delta_minor)
                                        <strong>+{{ \App\CatalogMoney::format($option->price_delta_minor) }}</strong>
                                    @endif
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                @endif
            @endforeach
            <textarea class="form-input min-h-24" name="notes" placeholder="Nota opcional"></textarea>
            <button class="button-primary w-full">Añadir al carrito</button>
        </form>
    </div>
</article>
@endsection
