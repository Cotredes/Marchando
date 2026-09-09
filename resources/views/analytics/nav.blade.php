<nav class="mb-6 flex gap-2 overflow-x-auto" aria-label="Control del negocio">
    @foreach (['analytics' => 'Panel', 'sales' => 'Ventas', 'invoices' => 'Facturas', 'customers' => 'Clientes', 'stock' => 'Stock', 'audit' => 'Auditoría'] as $key => $label)
        <a href="{{ route('restaurant.'.$key, $restaurant) }}" class="rounded-full border px-4 py-2 text-sm font-semibold {{ request()->routeIs('restaurant.'.$key.'*') || (request()->routeIs('restaurant.exports.*') && $key === 'analytics') ? 'border-orange-500 bg-orange-500 text-white' : 'border-stone-200 bg-white text-stone-700' }}">{{ $label }}</a>
    @endforeach
</nav>
