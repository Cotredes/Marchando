@extends('layouts.app', ['title' => 'Pedidos'])

@section('content')
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="eyebrow">Operación omnicanal</p>
            <h1 class="text-3xl font-semibold">Pedidos públicos</h1>
            <p class="mt-2 text-stone-500">QR, Take Away y Delivery en una única bandeja.</p>
        </div>
        <a class="button-secondary" href="{{ route('restaurant.pos', $restaurant) }}">TPV</a>
    </div>
    @if (session('status'))
        <div class="mt-5 rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="mt-5 rounded-xl bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
    @endif
    <div class="mt-6 space-y-3">
        @forelse ($requests as $request)
            <article class="rounded-2xl border border-stone-200 bg-white p-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-widest text-orange-700">{{ $request->channel === 'dine_in' ? 'QR · '.$request->table?->name : strtoupper($request->channel) }}</p>
                        <h2 class="mt-1 text-xl font-semibold">{{ $request->customer_name ?: 'Cliente anónimo' }}</h2>
                        <p class="text-sm text-stone-500">{{ $request->lines->sum('quantity') }} productos · {{ \App\CatalogMoney::format($request->total_minor) }} {{ $request->currency }}</p>
                    </div>
                    <span class="rounded-full bg-stone-100 px-3 py-1 text-sm">{{ $request->status }}</span>
                    @php($intent = $intents[$request->id] ?? null)
                    @if ($intent?->status === 'succeeded')
                        <span class="rounded-full bg-emerald-100 px-3 py-1 text-sm font-semibold text-emerald-800">Pagado online</span>
                    @endif
                </div>
                @if ($request->status === 'pending')
                    <div class="mt-4 flex flex-wrap gap-2">
                        <form method="POST" action="{{ route('restaurant.orders.accept', [$restaurant, $request]) }}" class="flex flex-wrap gap-2">
                            @csrf
                            <input class="form-input w-28" name="employee_id" type="number" placeholder="ID empleado" required>
                            <input class="form-input w-28" name="pin" type="password" placeholder="PIN" required>
                            <button class="button-primary">Aceptar</button>
                        </form>
                        <form method="POST" action="{{ route('restaurant.orders.reject', [$restaurant, $request]) }}" class="flex flex-wrap gap-2">
                            @csrf
                            <input class="form-input w-40" name="reason" value="No disponible">
                            <input class="form-input w-28" name="employee_id" type="number" placeholder="ID encargado">
                            <input class="form-input w-28" name="pin" type="password" placeholder="PIN">
                            <button class="button-secondary">Rechazar</button>
                        </form>
                        @if ($intent?->status === 'succeeded')
                            <p class="mt-2 text-sm text-amber-800">Pagado online: al rechazar se reembolsa automáticamente (requiere encargado).</p>
                        @endif
                    </div>
                @endif
            </article>
        @empty
            <div class="rounded-2xl border border-dashed border-stone-300 p-10 text-center text-stone-500">No hay pedidos públicos.</div>
        @endforelse
    </div>
    {{ $requests->links() }}
@endsection
