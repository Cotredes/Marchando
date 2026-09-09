<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Iniciar sesión · Marchando</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-stone-950 text-stone-950 antialiased">
    <main class="grid min-h-screen lg:grid-cols-[minmax(0,1fr)_minmax(420px,0.8fr)]">
        <section class="hidden flex-col justify-between bg-orange-500 p-10 text-white lg:flex xl:p-16">
            <div class="flex items-center gap-3 text-lg font-semibold tracking-tight">
                <span class="flex size-9 items-center justify-center rounded-xl bg-white text-sm font-bold text-orange-500">M</span>
                Marchando
            </div>
            <div class="max-w-lg">
                <p class="mb-5 text-sm font-semibold uppercase tracking-[0.18em] text-orange-100">Operación clara, servicio en marcha</p>
                <h1 class="text-5xl font-semibold leading-[1.05] tracking-[-0.04em] xl:text-6xl">Todo tu restaurante, en un mismo lugar.</h1>
                <p class="mt-7 max-w-md text-lg leading-8 text-orange-50">Una base sencilla para coordinar cada parte de tu negocio y avanzar con más calma.</p>
            </div>
            <p class="text-sm text-orange-100">Gestión para restaurantes que siguen avanzando.</p>
        </section>

        <section class="flex items-center justify-center bg-stone-50 px-5 py-10 sm:px-10">
            <div class="w-full max-w-md">
                <div class="mb-10 lg:hidden">
                    <div class="flex items-center gap-3 text-lg font-semibold tracking-tight">
                        <span class="flex size-9 items-center justify-center rounded-xl bg-orange-500 text-sm font-bold text-white">M</span>
                        Marchando
                    </div>
                </div>
                <p class="text-sm font-semibold uppercase tracking-[0.16em] text-orange-600">Bienvenido de nuevo</p>
                <h1 class="mt-3 text-3xl font-semibold tracking-tight text-stone-950">Entra en tu panel</h1>
                <p class="mt-3 text-stone-500">Continúa gestionando tu restaurante desde aquí.</p>

                @if ($errors->any())
                    <div class="mt-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">{{ $errors->first() }}</div>
                @endif

                <form method="POST" action="{{ route('login.store') }}" class="mt-8 space-y-5">
                    @csrf
                    <div>
                        <label for="email" class="mb-2 block text-sm font-semibold text-stone-700">Correo electrónico</label>
                        <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="email" class="form-input" />
                        @error('email')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <div class="mb-2 flex items-center justify-between">
                            <label for="password" class="block text-sm font-semibold text-stone-700">Contraseña</label>
                        </div>
                        <input id="password" name="password" type="password" required autocomplete="current-password" class="form-input" />
                        @error('password')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <label class="flex items-center gap-2 text-sm text-stone-500">
                        <input type="checkbox" name="remember" value="1" class="rounded border-stone-300 text-orange-500 focus:ring-orange-500">
                        Mantener la sesión iniciada
                    </label>
                    <button type="submit" class="w-full rounded-xl bg-stone-950 px-4 py-3.5 text-sm font-semibold text-white transition hover:bg-stone-800 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-2">Entrar al panel</button>
                </form>
            </div>
        </section>
    </main>
</body>
</html>
