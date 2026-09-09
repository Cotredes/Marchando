@extends('layouts.app', ['title' => 'Carta', 'heading' => 'Carta'])

@php
    $channelLabels = ['dine_in' => 'Local', 'takeaway' => 'Take Away', 'delivery' => 'Delivery'];
    $enabledChannels = ['dine_in' => $restaurant->dine_in_enabled, 'takeaway' => $restaurant->takeaway_enabled, 'delivery' => $restaurant->delivery_enabled];
@endphp

@section('content')
    <div class="flex flex-wrap items-start justify-between gap-5">
        <div><p class="eyebrow">Catálogo</p><h2 class="mt-1 text-3xl font-semibold tracking-tight">Carta</h2><p class="mt-3 max-w-2xl text-stone-500">Organiza categorías y productos para que tu equipo siempre tenga una oferta clara y actualizada.</p></div>
        @if ($canManage)<div class="flex flex-wrap gap-3"><a href="{{ route('restaurant.menu.categories.create', $restaurant) }}" class="button-secondary">Nueva categoría</a><a href="{{ route('restaurant.menu.products.create', $restaurant) }}" class="button-primary">Nuevo producto</a></div>@endif
    </div>

    @if (session('status'))<div class="mt-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800" role="status">{{ session('status') }}</div>@endif
    @if ($errors->any())<div class="mt-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">{{ $errors->first() }}</div>@endif

    <div class="mt-8 flex gap-2 border-b border-stone-200"><a href="{{ route('restaurant.menu', $restaurant) }}" class="local-tab local-tab-active" aria-current="page">Productos</a><a href="{{ route('restaurant.menu.categories.index', $restaurant) }}" class="local-tab">Categorías</a><a href="{{ route('restaurant.menu.modifier-groups.index', $restaurant) }}" class="local-tab">Modificadores</a></div>

    @if ($categories->isEmpty())
        <section class="empty-panel mt-8"><span class="empty-icon">+</span><h3 class="mt-5 text-xl font-semibold">Empieza por crear una categoría</h3><p class="mt-2 max-w-md text-sm leading-6 text-stone-500">Los productos necesitan una categoría para formar una carta ordenada.</p><a href="{{ route('restaurant.menu.categories.create', $restaurant) }}" class="button-primary mt-6">Crear primera categoría</a></section>
    @else
        <form method="GET" action="{{ route('restaurant.menu', $restaurant) }}" class="mt-6 rounded-2xl border border-stone-200 bg-white p-4" data-filter-form>
            <div class="grid gap-3 md:grid-cols-[minmax(0,1fr)_180px_160px_160px_auto] md:items-end">
                <div><label for="q" class="field-label">Buscar productos</label><input id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Nombre, descripción…" class="form-input"></div>
                <div><label for="category" class="field-label">Categoría</label><select id="category" name="category" class="form-input"><option value="">Todas</option>@foreach ($categories as $category)<option value="{{ $category->id }}" @selected(($filters['category'] ?? '') == $category->id)>{{ $category->name }}</option>@endforeach</select></div>
                <div><label for="status" class="field-label">Estado</label><select id="status" name="status" class="form-input"><option value="">Todos</option><option value="active" @selected(($filters['status'] ?? '') === 'active')>Activos</option><option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Inactivos</option></select></div>
                <div><label for="availability" class="field-label">Disponibilidad</label><select id="availability" name="availability" class="form-input"><option value="">Todas</option><option value="available" @selected(($filters['availability'] ?? '') === 'available')>Disponibles</option><option value="unavailable" @selected(($filters['availability'] ?? '') === 'unavailable')>No disponibles</option></select></div>
                <button type="submit" class="button-primary">Filtrar</button>
            </div>
            @if ($enabledChannels['takeaway'] || $enabledChannels['delivery'])<div class="mt-4 flex flex-wrap items-center gap-3 border-t border-stone-100 pt-4"><label for="channel" class="text-sm font-semibold text-stone-700">Canal</label><select id="channel" name="channel" class="rounded-lg border-stone-200 bg-stone-50 px-3 py-2 text-sm"><option value="">Todos</option>@foreach ($enabledChannels as $channel => $enabled) @if ($enabled)<option value="{{ $channel }}" @selected(($filters['channel'] ?? '') === $channel)>{{ $channelLabels[$channel] }}</option>@endif @endforeach</select><a href="{{ route('restaurant.menu', $restaurant) }}" class="text-sm font-semibold text-orange-700">Limpiar filtros</a></div>@endif
        </form>

        @if ($products->isEmpty())
            <section class="empty-panel mt-8"><span class="empty-icon">⌕</span><h3 class="mt-5 text-xl font-semibold">No encontramos productos</h3><p class="mt-2 text-sm text-stone-500">Prueba a cambiar los filtros o crea un producto nuevo.</p>@if ($canManage)<a href="{{ route('restaurant.menu.products.create', $restaurant) }}" class="button-primary mt-6">Crear producto</a>@endif</section>
        @else
            <div class="mt-5 flex items-center justify-between gap-4 text-sm text-stone-500"><p>{{ $products->total() }} {{ $products->total() === 1 ? 'producto' : 'productos' }}</p><p>Mostrando {{ $products->firstItem() }}–{{ $products->lastItem() }}</p></div>
            <div class="mt-3 overflow-hidden rounded-2xl border border-stone-200 bg-white">
                <div class="hidden grid-cols-[minmax(0,1.5fr)_minmax(120px,0.8fr)_100px_150px_120px_130px] gap-4 border-b border-stone-100 bg-stone-50 px-5 py-3 text-xs font-semibold uppercase tracking-wide text-stone-400 md:grid"><span>Producto</span><span>Categoría</span><span>Precio</span><span>Alérgenos</span><span>Canales</span><span>Estado</span></div>
                <div class="divide-y divide-stone-100">
                    @foreach ($products as $product)
                        <article class="grid gap-3 px-5 py-4 md:grid-cols-[minmax(0,1.5fr)_minmax(120px,0.8fr)_100px_150px_120px_130px] md:items-center md:gap-4">
                            <div class="flex min-w-0 items-center gap-3"><div class="flex size-11 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-orange-50 text-orange-600">@if ($product->image_path)<img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($product->image_path) }}" alt="" class="size-full object-cover">@else<span class="text-lg">✦</span>@endif</div><div class="min-w-0"><a href="{{ route('restaurant.menu.products.edit', [$restaurant, $product]) }}" class="truncate font-semibold text-stone-900 hover:text-orange-600">{{ $product->name }}</a><p class="truncate text-sm text-stone-500">{{ $product->description ?: 'Sin descripción' }}</p></div></div>
                            <div class="text-sm text-stone-600">{{ $product->category->name }}</div>
                            <div class="font-semibold">{{ \App\CatalogMoney::format($product->price_minor) }} €</div>
                            <div class="flex flex-wrap gap-1">@forelse ($product->allergens->take(3) as $allergen)<span class="badge">{{ $allergen->name }}</span>@empty<span class="text-sm text-stone-400">Sin indicar</span>@endforelse</div>
                            <div class="flex flex-wrap gap-1">@foreach ($enabledChannels as $channel => $enabled) @if ($enabled && $product->{'available_'.$channel})<span class="badge badge-orange">{{ $channelLabels[$channel] }}</span>@endif @endforeach</div>
                            <div class="flex items-center justify-between gap-2 md:block">@if ($canManage)<form method="POST" action="{{ route('restaurant.menu.products.availability', [$restaurant, $product]) }}">@csrf @method('PATCH')<input type="hidden" name="is_available" value="{{ $product->is_available ? 0 : 1 }}"><button class="availability-button {{ $product->is_available ? 'availability-on' : 'availability-off' }}" aria-label="{{ $product->is_available ? 'Marcar no disponible' : 'Marcar disponible' }}">{{ $product->is_available ? 'Disponible' : 'No disponible' }}</button></form><a href="{{ route('restaurant.menu.products.edit', [$restaurant, $product]) }}" class="mt-2 block text-sm font-semibold text-orange-700 md:hidden">Editar</a>@else<span class="availability-button {{ $product->is_available ? 'availability-on' : 'availability-off' }}">{{ $product->is_available ? 'Disponible' : 'No disponible' }}</span>@endif</div>
                        </article>
                    @endforeach
                </div>
            </div>
            <div class="mt-5">{{ $products->links() }}</div>
        @endif
    @endif
@endsection
