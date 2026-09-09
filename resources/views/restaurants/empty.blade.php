<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sin restaurante · Marchando</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-stone-50 text-stone-950 antialiased">
    <main class="mx-auto flex min-h-screen max-w-2xl flex-col justify-center px-6 py-12">
        <a href="{{ route('app.index') }}" class="text-lg font-semibold tracking-tight">Marchando</a>
        <section class="mt-8 rounded-2xl border border-dashed border-stone-300 bg-white px-6 py-12 sm:px-10">
            <span class="text-sm font-semibold uppercase tracking-[0.14em] text-orange-600">Todavía no hay un restaurante</span>
            <h1 class="mt-3 text-3xl font-semibold tracking-tight">Tu cuenta está lista para empezar.</h1>
            <p class="mt-4 text-lg leading-8 text-stone-500">Cuando tengas un restaurante asociado a tu cuenta, podrás gestionarlo desde este espacio.</p>
            <form method="POST" action="{{ route('logout') }}" class="mt-8">
                @csrf
                <button class="text-sm font-semibold text-stone-700 underline decoration-stone-300 underline-offset-4 hover:text-orange-600">Cerrar sesión</button>
            </form>
        </section>
    </main>
</body>
</html>
