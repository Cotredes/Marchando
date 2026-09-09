@extends('layouts.admin', ['title' => 'Crear usuario', 'heading' => 'Crear usuario'])

@section('content')
    <form method="POST" action="{{ route('admin.users.store') }}" class="max-w-3xl rounded-2xl border border-stone-200 bg-white">
        <div class="settings-card-body grid gap-5 sm:grid-cols-2">
            @csrf
            <div>
                <label class="field-label" for="name">Nombre</label>
                <input class="form-input" id="name" name="name" required maxlength="160" value="{{ old('name') }}">
                @error('name')<p class="field-message">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="field-label" for="email">Email</label>
                <input class="form-input" id="email" type="email" name="email" required maxlength="160" value="{{ old('email') }}">
                @error('email')<p class="field-message">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="field-label" for="password">Contraseña</label>
                <input class="form-input" id="password" type="password" name="password" required minlength="8" autocomplete="new-password">
                @error('password')<p class="field-message">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="field-label" for="password_confirmation">Confirmar contraseña</label>
                <input class="form-input" id="password_confirmation" type="password" name="password_confirmation" required minlength="8" autocomplete="new-password">
            </div>
            <div>
                <label class="field-label" for="restaurant_id">Restaurante</label>
                <select class="form-input" id="restaurant_id" name="restaurant_id" required>
                    @foreach ($restaurants as $restaurant)
                        <option value="{{ $restaurant->id }}" @selected(old('restaurant_id') == $restaurant->id)>{{ $restaurant->name }}</option>
                    @endforeach
                </select>
                @error('restaurant_id')<p class="field-message">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="field-label" for="role">Rol en el restaurante</label>
                <select class="form-input" id="role" name="role" required>
                    <option value="member" @selected(old('role') === 'member')>Miembro</option>
                    <option value="owner" @selected(old('role') === 'owner')>Administrador</option>
                </select>
                <p class="field-help">Administrador gestiona ese restaurante. No otorga acceso global a Marchando.</p>
                @error('role')<p class="field-message">{{ $message }}</p>@enderror
            </div>
        </div>
        <div class="settings-card-footer flex justify-between">
            <a class="button-secondary" href="{{ route('admin.users.index') }}">Volver</a>
            <button class="button-primary">Crear usuario</button>
        </div>
    </form>
@endsection
