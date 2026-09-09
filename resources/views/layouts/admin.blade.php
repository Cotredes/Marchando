<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Administración' }} · Marchando</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-stone-50 text-stone-950 antialiased">
    <div class="flex min-h-screen flex-col md:flex-row">
        <aside class="flex w-full shrink-0 flex-col border-b border-stone-200 bg-white md:min-h-screen md:w-64 md:border-b-0 md:border-r">
            <div class="px-5 py-5 md:px-6 md:py-7">
                <a href="{{ route('admin.dashboard') }}" class="inline-flex items-center gap-3 text-lg font-semibold tracking-tight">
                    <span class="flex size-9 items-center justify-center rounded-xl bg-orange-500 text-sm font-bold text-white">M</span>
                    Marchando
                </a>
                <span class="mt-3 hidden text-xs font-medium uppercase tracking-[0.16em] text-stone-400 md:block">Administración global</span>
            </div>
            <nav class="flex gap-1 overflow-x-auto px-4 py-4 md:block md:space-y-1 md:px-4 md:py-7" aria-label="Navegación de administración">
                <a href="{{ route('admin.dashboard') }}" class="nav-link {{ request()->routeIs('admin.dashboard') ? 'nav-link-active' : '' }}">Resumen</a>
                <a href="{{ route('admin.restaurants.index') }}" class="nav-link {{ request()->routeIs('admin.restaurants.*') ? 'nav-link-active' : '' }}">Restaurantes</a>
                <a href="{{ route('admin.users.index') }}" class="nav-link {{ request()->routeIs('admin.users.*') ? 'nav-link-active' : '' }}">Usuarios</a>
                <a href="{{ route('app.index') }}" class="nav-link">Volver al panel</a>
            </nav>
            <div class="mt-auto border-t border-stone-100 p-4">
                <div class="flex items-center gap-3 px-2 py-2">
                    <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-stone-900 text-xs font-semibold text-white">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold">{{ auth()->user()->name }}</p>
                        <p class="truncate text-xs text-stone-500">{{ auth()->user()->email }}</p>
                        <p class="mt-1 inline-block rounded-full bg-orange-100 px-2 py-0.5 text-[11px] font-semibold text-orange-800">Propietario de Marchando</p>
                    </div>
                </div>
                <form method="POST" action="{{ route('logout') }}" class="mt-2">
                    @csrf
                    <button type="submit" class="w-full rounded-lg px-2 py-2 text-left text-sm font-medium text-stone-500 transition hover:bg-stone-100 hover:text-stone-900">Cerrar sesión</button>
                </form>
                <p class="mt-2 px-2 text-[11px] text-stone-400">Marchando v{{ config('app.marchando_version') }}</p>
            </div>
        </aside>
        <main class="min-w-0 flex-1">
            <header class="border-b border-stone-200 bg-white">
                <div class="mx-auto max-w-7xl px-5 py-5 sm:px-8 lg:px-10">
                    <h1 class="text-xl font-semibold tracking-tight">{{ $heading ?? ($title ?? 'Administración') }}</h1>
                </div>
            </header>
            <div class="mx-auto max-w-7xl px-5 py-8 sm:px-8 lg:px-10 lg:py-10">
                @if (session('status'))
                    <p class="mb-6 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-800">{{ session('status') }}</p>
                @endif
                @if ($errors->any())
                    <div class="mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                        <ul class="list-disc space-y-1 pl-5">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                @yield('content')
            </div>
        </main>
    </div>
</body>
</html>
