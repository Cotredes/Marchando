@extends('layouts.admin', ['title' => 'Restaurantes', 'heading' => 'Restaurantes'])

@section('content')
    <div class="mb-6 flex items-center justify-between gap-4">
        <p class="text-sm text-stone-500">{{ $restaurants->count() }} restaurantes. Los datos de cada uno se gestionan entrando en su panel.</p>
        <a href="{{ route('admin.restaurants.create') }}" class="button-primary">Crear restaurante</a>
    </div>
    <ul class="grid gap-3 lg:grid-cols-2">
        @foreach ($restaurants as $restaurant)
            <li class="rounded-2xl border border-stone-200 bg-white p-5">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="font-semibold">{{ $restaurant->name }}</h2>
                        <p class="mt-1 text-sm text-stone-500">
                            {{ $restaurant->users_count }} usuarios · {{ $restaurant->dining_tables_count }} mesas · {{ $restaurant->employees_count }} empleados
                        </p>
                    </div>
                    @if ($restaurant->is_active)
                        <span class="rounded-full bg-green-100 px-2.5 py-1 text-xs font-semibold text-green-800">Activo</span>
                    @else
                        <span class="rounded-full bg-stone-200 px-2.5 py-1 text-xs font-semibold text-stone-600">Inactivo</span>
                    @endif
                </div>
                <div class="mt-4 flex flex-wrap gap-2">
                    <a href="{{ route('admin.restaurants.enter', $restaurant) }}" class="button-primary">Entrar</a>
                    <a href="{{ route('admin.restaurants.edit', $restaurant) }}" class="button-secondary">Gestionar</a>
                    <form method="POST" action="{{ route('admin.restaurants.toggle', $restaurant) }}">
                        @csrf
                        <button class="button-secondary" @if (! $restaurant->is_active) onclick="return confirm('¿Reactivar este restaurante?')" @else onclick="return confirm('¿Desactivar este restaurante? Se conserva todo su histórico.')" @endif>
                            {{ $restaurant->is_active ? 'Desactivar' : 'Activar' }}
                        </button>
                    </form>
                </div>
            </li>
        @endforeach
    </ul>
@endsection
