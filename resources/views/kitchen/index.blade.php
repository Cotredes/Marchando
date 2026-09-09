@extends('layouts.kitchen', ['title' => 'Cocina'])

@section('content')
    <div data-kds-root data-feed-url="{{ route('restaurant.kitchen.feed', $restaurant) }}">
        <header class="kds-header">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[.16em] text-orange-300">KDS · {{ $restaurant->name }}</p>
                <h1 class="mt-1 text-3xl font-semibold">Cocina</h1>
            </div>
            <div class="flex items-center gap-2">
                <span data-kds-connection class="kds-connection kds-connection-online">En línea</span>
                <a class="button-secondary" href="{{ route('restaurant.pos', $restaurant) }}">TPV</a>
            </div>
        </header>
        <nav class="my-5 flex gap-2 overflow-x-auto">
            <a class="pos-category-button {{ ! $station ? 'pos-category-button-active' : '' }}" href="{{ route('restaurant.kitchen', $restaurant) }}">Todas</a>
            @foreach ($stations as $itemStation)
                <a class="pos-category-button {{ $station === $itemStation->id ? 'pos-category-button-active' : '' }}" href="{{ route('restaurant.kitchen', [$restaurant, 'station' => $itemStation->id]) }}">{{ $itemStation->name }}</a>
            @endforeach
        </nav>
        <section data-kds-board class="kds-board">
            @forelse ($items as $item)
                <article class="kds-ticket kds-ticket-{{ $item->status }}" data-kds-item="{{ $item->id }}">
                    <div class="kds-ticket-header">
                        <div>
                            <h2 class="text-2xl font-bold">{{ $item->dispatch->fulfillment_label }}</h2>
                            <p class="text-sm text-stone-400">{{ $item->dispatch->channel }} · {{ $item->station_name }}</p>
                        </div>
                        <time class="kds-timer" data-elapsed-since="{{ $item->queued_at->toISOString() }}">00:00</time>
                    </div>
                    <div class="kds-line">
                        <span class="kds-line-quantity">{{ $item->activeQuantity() }}×</span>
                        <div>
                            <p class="text-xl font-semibold">{{ $item->product_name }}</p>
                            @if ($item->format_name)
                                <p class="text-orange-300">{{ $item->format_name }}</p>
                            @endif
                            @foreach ($item->modifiers ?? [] as $modifier)
                                <p class="kds-line-modifiers">{{ $modifier['quantity'] }}× {{ $modifier['option'] }}</p>
                            @endforeach
                            @if ($item->notes)
                                <p class="mt-2 text-sm text-orange-200">{{ $item->notes }}</p>
                            @endif
                        </div>
                    </div>
                </article>
            @empty
                <div class="rounded-2xl border border-stone-700 p-8 text-center text-stone-400">No hay comandas pendientes.</div>
            @endforelse
        </section>
    </div>
@endsection
