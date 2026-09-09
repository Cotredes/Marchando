<nav class="mt-6 flex flex-wrap gap-2 border-b border-stone-200 pb-3" aria-label="Personal">
    <a class="local-tab {{ request()->routeIs('restaurant.staff') ? 'local-tab-active' : '' }}" href="{{ route('restaurant.staff', $restaurant) }}">Control horario</a>
    <a class="local-tab {{ request()->routeIs('restaurant.staff.employees.*') ? 'local-tab-active' : '' }}" href="{{ route('restaurant.staff.employees.index', $restaurant) }}">Empleados</a>
    <a class="local-tab" href="{{ route('restaurant.staff.clock', $restaurant) }}">Terminal de fichaje</a>
</nav>
