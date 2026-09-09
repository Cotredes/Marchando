<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Panel' }} · Marchando</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-stone-50 text-stone-950 antialiased">
    <div class="flex min-h-screen flex-col md:flex-row">
        <aside class="flex w-full shrink-0 flex-col border-b border-stone-200 bg-white md:min-h-screen md:w-64 md:border-b-0 md:border-r">
            <div class="flex items-center justify-between px-5 py-5 md:block md:px-6 md:py-7">
                <a href="{{ route('restaurant.dashboard', $restaurant) }}" class="inline-flex items-center gap-3 text-lg font-semibold tracking-tight">
                    <span class="flex size-9 items-center justify-center rounded-xl bg-orange-500 text-sm font-bold text-white">M</span>
                    Marchando
                </a>
                <span class="mt-3 hidden text-xs font-medium uppercase tracking-[0.16em] text-stone-400 md:block">Panel de gestión</span>
            </div>

            <div class="mx-4 rounded-xl bg-stone-50 px-4 py-3 md:mx-5">
                <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-stone-400">Restaurante actual</p>
                <p class="mt-1 truncate text-sm font-semibold text-stone-800">{{ $restaurant->name }}</p>
            </div>

            <nav class="flex gap-1 overflow-x-auto px-4 py-4 md:block md:space-y-1 md:px-4 md:py-7" aria-label="Navegación principal">
                    <a href="{{ route('restaurant.dashboard', $restaurant) }}" class="nav-link {{ request()->routeIs('restaurant.dashboard') ? 'nav-link-active' : '' }}" @if (request()->routeIs('restaurant.dashboard')) aria-current="page" @endif>Inicio</a>
                @foreach (\App\Http\Controllers\DashboardController::modules() as $key => $module)
                    <a href="{{ route('restaurant.'.$key, $restaurant) }}" class="nav-link {{ request()->routeIs('restaurant.'.$key.'*') ? 'nav-link-active' : '' }}" @if (request()->routeIs('restaurant.'.$key.'*')) aria-current="page" @endif>{{ $module['label'] }}</a>
                @endforeach
                @if (auth()->user()->isPlatformOwner())
                    <a href="{{ route('admin.dashboard') }}" class="nav-link">Administración</a>
                @endif
            </nav>

            <div class="mt-auto border-t border-stone-100 p-4">
                <div class="flex items-center gap-3 px-2 py-2">
                    <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-stone-900 text-xs font-semibold text-white">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold">{{ auth()->user()->name }}</p>
                        <p class="truncate text-xs text-stone-500">{{ auth()->user()->email }}</p>
                        @if (auth()->user()->isPlatformOwner())
                            <p class="mt-1 inline-block rounded-full bg-orange-100 px-2 py-0.5 text-[11px] font-semibold text-orange-800">Propietario de Marchando</p>
                        @endif
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
                <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-5 py-5 sm:px-8 lg:px-10">
                    <div>
                        <p class="text-sm text-stone-500">{{ $restaurant->name }}</p>
                        <h1 class="mt-1 text-xl font-semibold tracking-tight">{{ $heading ?? 'Panel' }}</h1>
                    </div>
                    @php($switchableRestaurants = auth()->user()->isPlatformOwner() ? \App\Models\Restaurant::query()->orderBy('name')->get() : auth()->user()->restaurants()->orderBy('name')->get())
                    @if ($switchableRestaurants->count() > 1)
                        <label class="hidden items-center gap-2 text-sm text-stone-500 sm:flex">
                            <span class="sr-only">Cambiar restaurante</span>
                            <select onchange="window.location.href = this.value" class="rounded-lg border-stone-200 bg-stone-50 py-2 pl-3 pr-8 text-sm font-medium text-stone-700 focus:border-orange-500 focus:ring-orange-500">
                                @foreach ($switchableRestaurants as $availableRestaurant)
                                    <option value="{{ route('restaurant.dashboard', $availableRestaurant) }}" @selected($availableRestaurant->is($restaurant))>{{ $availableRestaurant->name }}</option>
                                @endforeach
                            </select>
                        </label>
                    @endif
                </div>
            </header>
            <div class="mx-auto max-w-7xl px-5 py-8 sm:px-8 lg:px-10 lg:py-10">
                @yield('content')
            </div>
        </main>
    </div>
</body>
</html>
