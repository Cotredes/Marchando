@extends('layouts.admin', ['title' => $restaurant->exists ? 'Gestionar restaurante' : 'Crear restaurante', 'heading' => $restaurant->exists ? $restaurant->name : 'Crear restaurante'])

@section('content')
    <p class="max-w-3xl text-sm text-stone-500">
        @if ($restaurant->exists)
            Datos administrativos. La configuración operativa (carta, zonas, mesas, horarios, integraciones) se hace entrando en el restaurante y usando sus módulos.
        @else
            Datos mínimos para dar de alta el restaurante. Después entra en él y configúralo con los módulos normales.
        @endif
    </p>
    <form method="POST" action="{{ $restaurant->exists ? route('admin.restaurants.update', $restaurant) : route('admin.restaurants.store') }}" class="mt-6 max-w-3xl rounded-2xl border border-stone-200 bg-white">
        <div class="settings-card-body grid gap-5 sm:grid-cols-2">
            @csrf
            @if ($restaurant->exists)
                @method('PATCH')
            @endif
            <div>
                <label class="field-label" for="name">Nombre</label>
                <input class="form-input" id="name" name="name" required maxlength="160" value="{{ old('name', $restaurant->name) }}">
                @error('name')<p class="field-message">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="field-label" for="slug">Slug</label>
                <input class="form-input" id="slug" name="slug" maxlength="160" @if ($restaurant->exists) required @endif value="{{ old('slug', $restaurant->slug) }}" placeholder="se-genera-del-nombre">
                <p class="field-help">Identificador en las URLs del panel.</p>
                @error('slug')<p class="field-message">{{ $message }}</p>@enderror
            </div>
            @if ($restaurant->exists)
                <div>
                    <label class="field-label" for="contact_email">Email de contacto</label>
                    <input class="form-input" id="contact_email" type="email" name="contact_email" maxlength="160" value="{{ old('contact_email', $restaurant->contact_email) }}">
                    @error('contact_email')<p class="field-message">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="field-label" for="phone">Teléfono</label>
                    <input class="form-input" id="phone" name="phone" maxlength="40" value="{{ old('phone', $restaurant->phone) }}">
                    @error('phone')<p class="field-message">{{ $message }}</p>@enderror
                </div>
                <div class="sm:col-span-2">
                    <label class="field-label" for="address">Dirección</label>
                    <input class="form-input" id="address" name="address" maxlength="255" value="{{ old('address', $restaurant->address) }}">
                </div>
                <div>
                    <label class="field-label" for="city">Ciudad</label>
                    <input class="form-input" id="city" name="city" maxlength="120" value="{{ old('city', $restaurant->city) }}">
                </div>
                <div>
                    <label class="field-label" for="province">Provincia</label>
                    <input class="form-input" id="province" name="province" maxlength="120" value="{{ old('province', $restaurant->province) }}">
                </div>
            @endif
            <div>
                <label class="field-label" for="timezone">Zona horaria</label>
                <input class="form-input" id="timezone" name="timezone" required value="{{ old('timezone', $restaurant->timezone ?? 'Europe/Madrid') }}">
                @error('timezone')<p class="field-message">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="field-label" for="currency">Moneda (ISO)</label>
                <input class="form-input" id="currency" name="currency" required minlength="3" maxlength="3" value="{{ old('currency', $restaurant->currency ?? 'EUR') }}">
                @error('currency')<p class="field-message">{{ $message }}</p>@enderror
            </div>
        </div>
        <div class="settings-card-footer flex justify-between">
            <a class="button-secondary" href="{{ route('admin.restaurants.index') }}">Volver</a>
            <button class="button-primary">{{ $restaurant->exists ? 'Guardar cambios' : 'Crear restaurante' }}</button>
        </div>
    </form>
    @if ($restaurant->exists)
        <div class="mt-6 max-w-3xl rounded-2xl border border-stone-200 bg-white p-5">
            <h2 class="font-semibold">Acceso al restaurante</h2>
            <p class="mt-1 text-sm text-stone-500">Entra para configurarlo con los módulos normales o cambia su estado. No es posible eliminarlo: desactivar conserva el histórico.</p>
            <div class="mt-4 flex flex-wrap gap-2">
                <a href="{{ route('admin.restaurants.enter', $restaurant) }}" class="button-primary">Entrar en {{ $restaurant->name }}</a>
                <form method="POST" action="{{ route('admin.restaurants.toggle', $restaurant) }}">
                    @csrf
                    <button class="button-secondary" onclick="return confirm('¿Cambiar el estado de este restaurante?')">
                        {{ $restaurant->is_active ? 'Desactivar' : 'Activar' }}
                    </button>
                </form>
            </div>
        </div>
    @endif
@endsection
