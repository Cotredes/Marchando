@extends('layouts.admin', ['title' => 'Resumen', 'heading' => 'Marchando'])

@section('content')
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-2xl border border-stone-200 bg-white p-5">
            <p class="text-sm text-stone-500">Restaurantes</p>
            <p class="mt-1 text-3xl font-semibold">{{ $restaurantCount }}</p>
        </div>
        <div class="rounded-2xl border border-stone-200 bg-white p-5">
            <p class="text-sm text-stone-500">Restaurantes activos</p>
            <p class="mt-1 text-3xl font-semibold">{{ $activeRestaurantCount }}</p>
        </div>
        <div class="rounded-2xl border border-stone-200 bg-white p-5">
            <p class="text-sm text-stone-500">Usuarios</p>
            <p class="mt-1 text-3xl font-semibold">{{ $userCount }}</p>
        </div>
        <div class="rounded-2xl border border-stone-200 bg-white p-5">
            <p class="text-sm text-stone-500">Usuarios activos</p>
            <p class="mt-1 text-3xl font-semibold">{{ $activeUserCount }}</p>
        </div>
    </div>

    <div class="mt-8 grid gap-3 sm:grid-cols-2">
        <a href="{{ route('admin.restaurants.index') }}" class="rounded-2xl border border-stone-200 bg-white p-5 transition hover:border-orange-300">
            <h2 class="font-semibold">Restaurantes</h2>
            <p class="mt-1 text-sm text-stone-500">Ver, crear, activar o desactivar restaurantes y entrar en cualquiera de ellos.</p>
        </a>
        <a href="{{ route('admin.users.index') }}" class="rounded-2xl border border-stone-200 bg-white p-5 transition hover:border-orange-300">
            <h2 class="font-semibold">Usuarios</h2>
            <p class="mt-1 text-sm text-stone-500">Crear usuarios, asignar accesos y roles, o desactivar cuentas sin perder histórico.</p>
        </a>
    </div>

    @if (! empty($attention))
        <section class="mt-8 rounded-2xl border border-red-200 bg-red-50 p-5" aria-label="Incidencias">
            <h2 class="font-semibold text-red-800">Incidencias ({{ count($attention) }})</h2>
            <ul class="mt-2 space-y-1 text-sm text-red-700">
                @foreach ($attention as $alert)
                    <li>
                        <span class="font-semibold">{{ $alert['restaurant']->name }}:</span>
                        <a class="underline" href="{{ route($alert['link'], $alert['restaurant']) }}">{{ $alert['label'] }}</a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    <section class="mt-8">
        <h2 class="text-lg font-semibold">Actividad reciente de administración</h2>
        @if ($audits->isEmpty())
            <p class="mt-2 text-sm text-stone-500">Todavía no hay acciones registradas.</p>
        @else
            <ul class="mt-3 divide-y divide-stone-100 rounded-2xl border border-stone-200 bg-white">
                @foreach ($audits as $audit)
                    <li class="px-5 py-3 text-sm">
                        <span class="font-semibold">{{ $audit->action }}</span>
                        @if ($audit->restaurant)
                            <span class="text-stone-500">· {{ $audit->restaurant->name }}</span>
                        @endif
                        @if ($audit->detail)
                            <span class="text-stone-500">· {{ $audit->detail }}</span>
                        @endif
                        <span class="block text-xs text-stone-400">{{ $audit->user?->email ?? '—' }} · {{ $audit->created_at?->format('d/m/Y H:i') }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
@endsection
