@extends('layouts.app', ['title' => 'Inicio', 'heading' => 'Buenos días, '.auth()->user()->name])

@section('content')
    <div class="max-w-3xl">
        <p class="text-lg leading-8 text-stone-600">Este es el punto de partida para gestionar <span class="font-semibold text-stone-900">{{ $restaurant->name }}</span>: pedidos, TPV, cocina, carta, personal, caja e integraciones.</p>
        @if (auth()->user()->isPlatformOwner())
            <a href="{{ route('admin.dashboard') }}" class="mt-4 inline-flex items-center gap-2 rounded-xl bg-stone-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-stone-700">Administrar Marchando →</a>
        @endif
    </div>

    @if (! empty($attention))
        <section class="mt-8 rounded-2xl border border-red-200 bg-red-50 p-5" aria-label="Requiere atención">
            <h2 class="font-semibold text-red-800">Requiere atención ({{ count($attention) }})</h2>
            <ul class="mt-2 space-y-1 text-sm text-red-700">
                @foreach ($attention as $alert)
                    <li><a class="underline" href="{{ route($alert['link'], $restaurant) }}">{{ $alert['label'] }}</a></li>
                @endforeach
            </ul>
        </section>
    @endif

    <section class="mt-10" aria-labelledby="readiness-title">
        <p class="text-sm font-semibold uppercase tracking-[0.14em] text-orange-600">Antes del servicio</p>
        <h2 id="readiness-title" class="mt-2 text-2xl font-semibold tracking-tight">Listo para operar</h2>
        <div class="mt-5 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($readiness as $check)
                <div class="rounded-2xl border bg-white p-4 {{ $check['ok'] ? 'border-stone-200' : 'border-amber-300 bg-amber-50' }}">
                    <p class="flex items-center gap-2 font-semibold">{{ $check['ok'] ? '✓' : '✕' }} {{ $check['label'] }}</p>
                    <p class="mt-1 text-sm text-stone-500">{{ $check['detail'] }}</p>
                    @if ($check['link'])
                        <a class="mt-2 inline-block text-sm font-semibold text-orange-700" href="{{ route($check['link'], $restaurant) }}">Revisar →</a>
                    @endif
                </div>
            @endforeach
        </div>
    </section>

    <section class="mt-10" aria-labelledby="quick-access-title">
        <div class="flex items-end justify-between gap-4">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.14em] text-orange-600">Accesos rápidos</p>
                <h2 id="quick-access-title" class="mt-2 text-2xl font-semibold tracking-tight">¿Dónde quieres ir?</h2>
            </div>
        </div>
        <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach (['orders', 'pos', 'menu', 'analytics'] as $key)
                @php($module = \App\Http\Controllers\DashboardController::modules()[$key])
                <a href="{{ route('restaurant.'.$key, $restaurant) }}" class="group rounded-2xl border border-stone-200 bg-white p-5 transition hover:-translate-y-0.5 hover:border-orange-300 hover:shadow-sm focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-2">
                    <span class="flex size-10 items-center justify-center rounded-xl bg-orange-50 text-orange-600 transition group-hover:bg-orange-500 group-hover:text-white">→</span>
                    <h3 class="mt-5 font-semibold">{{ $module['label'] }}</h3>
                    <p class="mt-2 text-sm leading-6 text-stone-500">{{ $module['description'] }}</p>
                </a>
            @endforeach
        </div>
    </section>
@endsection
