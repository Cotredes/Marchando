@extends('layouts.admin', ['title' => 'Gestionar usuario', 'heading' => $user->name])

@section('content')
    <form method="POST" action="{{ route('admin.users.update', $user) }}" class="max-w-3xl rounded-2xl border border-stone-200 bg-white">
        <div class="settings-card-body grid gap-5 sm:grid-cols-2">
            @csrf
            @method('PATCH')
            <div>
                <label class="field-label" for="name">Nombre</label>
                <input class="form-input" id="name" name="name" required maxlength="160" value="{{ old('name', $user->name) }}">
                @error('name')<p class="field-message">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="field-label" for="email">Email</label>
                <input class="form-input" id="email" type="email" name="email" required maxlength="160" value="{{ old('email', $user->email) }}">
                @error('email')<p class="field-message">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="field-label" for="password">Nueva contraseña (opcional)</label>
                <input class="form-input" id="password" type="password" name="password" minlength="8" autocomplete="new-password">
                @error('password')<p class="field-message">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="field-label" for="password_confirmation">Confirmar contraseña</label>
                <input class="form-input" id="password_confirmation" type="password" name="password_confirmation" minlength="8" autocomplete="new-password">
            </div>
        </div>
        <div class="settings-card-footer flex justify-between">
            <a class="button-secondary" href="{{ route('admin.users.index') }}">Volver</a>
            <button class="button-primary">Guardar cambios</button>
        </div>
    </form>

    <section class="mt-6 max-w-3xl rounded-2xl border border-stone-200 bg-white p-5">
        <h2 class="font-semibold">Restaurantes asociados</h2>
        @if ($user->isPlatformOwner())
            <p class="mt-1 text-sm text-stone-500">Es el propietario de Marchando: su acceso global no depende de estas asociaciones y no se gestionan desde aquí.</p>
        @endif
        <ul class="mt-3 space-y-3">
            @foreach ($user->restaurants as $restaurant)
                <li class="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-stone-50 px-4 py-3">
                    <span class="text-sm font-medium">{{ $restaurant->name }}</span>
                    @if ($user->isPlatformOwner())
                        <span class="text-sm text-stone-500">{{ $restaurant->pivot->role === 'owner' ? 'Administrador' : 'Miembro' }}</span>
                    @else
                        <span class="flex flex-wrap items-center gap-2">
                            <form method="POST" action="{{ route('admin.users.memberships.update', [$user, $restaurant]) }}" class="flex items-center gap-2">
                                @csrf
                                @method('PATCH')
                                <select name="role" class="rounded-lg border-stone-200 bg-white py-1.5 pl-3 pr-8 text-sm">
                                    <option value="member" @selected($restaurant->pivot->role === 'member')>Miembro</option>
                                    <option value="owner" @selected($restaurant->pivot->role === 'owner')>Administrador</option>
                                </select>
                                <button class="button-secondary">Cambiar rol</button>
                            </form>
                            <form method="POST" action="{{ route('admin.users.memberships.detach', [$user, $restaurant]) }}">
                                @csrf
                                @method('DELETE')
                                <button class="button-secondary" onclick="return confirm('¿Retirar el acceso a {{ $restaurant->name }}? El histórico se conserva.')">Quitar acceso</button>
                            </form>
                        </span>
                    @endif
                </li>
            @endforeach
        </ul>
        @unless ($user->isPlatformOwner())
            <form method="POST" action="{{ route('admin.users.memberships.attach', $user) }}" class="mt-4 flex flex-wrap items-end gap-3">
                @csrf
                <div>
                    <label class="field-label" for="restaurant_id">Añadir restaurante</label>
                    <select class="form-input" id="restaurant_id" name="restaurant_id" required>
                        @foreach ($restaurants as $restaurant)
                            @unless ($user->restaurants->contains($restaurant))
                                <option value="{{ $restaurant->id }}">{{ $restaurant->name }}</option>
                            @endunless
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="field-label" for="role">Rol</label>
                    <select class="form-input" id="role" name="role" required>
                        <option value="member">Miembro</option>
                        <option value="owner">Administrador</option>
                    </select>
                </div>
                <button class="button-primary">Asignar acceso</button>
            </form>
        @endunless
    </section>

    @unless ($user->isPlatformOwner())
        <section class="mt-6 max-w-3xl rounded-2xl border border-stone-200 bg-white p-5">
            <h2 class="font-semibold">Estado de la cuenta</h2>
            <p class="mt-1 text-sm text-stone-500">Desactivar impide iniciar sesión sin borrar nada: empleados, comandas, ventas y auditoría se conservan.</p>
            <form method="POST" action="{{ route('admin.users.toggle', $user) }}" class="mt-4">
                @csrf
                <button class="button-secondary" onclick="return confirm('¿Cambiar el estado de esta cuenta?')">
                    {{ $user->is_active ? 'Desactivar cuenta' : 'Activar cuenta' }}
                </button>
            </form>
        </section>
    @endunless
@endsection
