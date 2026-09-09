@extends('layouts.admin', ['title' => 'Usuarios', 'heading' => 'Usuarios'])

@section('content')
    <div class="mb-6 flex items-center justify-between gap-4">
        <p class="text-sm text-stone-500">{{ $users->count() }} usuarios en Marchando.</p>
        <a href="{{ route('admin.users.create') }}" class="button-primary">Crear usuario</a>
    </div>
    <ul class="grid gap-3 lg:grid-cols-2">
        @foreach ($users as $user)
            <li class="rounded-2xl border border-stone-200 bg-white p-5">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="font-semibold">{{ $user->name }}</h2>
                        <p class="text-sm text-stone-500">{{ $user->email }}</p>
                    </div>
                    <div class="flex flex-col items-end gap-1">
                        @if ($user->isPlatformOwner())
                            <span class="rounded-full bg-orange-100 px-2.5 py-1 text-xs font-semibold text-orange-800">Propietario de Marchando</span>
                        @endif
                        @if ($user->is_active)
                            <span class="rounded-full bg-green-100 px-2.5 py-1 text-xs font-semibold text-green-800">Activo</span>
                        @else
                            <span class="rounded-full bg-stone-200 px-2.5 py-1 text-xs font-semibold text-stone-600">Desactivado</span>
                        @endif
                    </div>
                </div>
                @if ($user->restaurants->isNotEmpty())
                    <ul class="mt-3 space-y-1 text-sm text-stone-600">
                        @foreach ($user->restaurants as $restaurant)
                            <li>{{ $restaurant->name }} · {{ $restaurant->pivot->role === 'owner' ? 'Administrador' : 'Miembro' }}</li>
                        @endforeach
                    </ul>
                @else
                    <p class="mt-3 text-sm text-stone-400">Sin restaurantes asociados.</p>
                @endif
                <div class="mt-4 flex flex-wrap gap-2">
                    <a href="{{ route('admin.users.edit', $user) }}" class="button-secondary">Gestionar</a>
                    @unless ($user->isPlatformOwner())
                        <form method="POST" action="{{ route('admin.users.toggle', $user) }}">
                            @csrf
                            <button class="button-secondary" onclick="return confirm('¿Cambiar el estado de esta cuenta? El histórico se conserva siempre.')">
                                {{ $user->is_active ? 'Desactivar' : 'Activar' }}
                            </button>
                        </form>
                    @endunless
                </div>
            </li>
        @endforeach
    </ul>
@endsection
