@extends('layouts.app', ['title' => $customer->display_name ?? 'Cliente', 'heading' => $customer->display_name ?? 'Cliente'])

@section('content')
<a class="text-sm font-semibold text-orange-700" href="{{ route('restaurant.customers', $restaurant) }}">← Volver a clientes</a>
@if (session('status'))<div class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>@endif
@if ($errors->any())<div class="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>@endif
<div class="mt-4 grid gap-6 lg:grid-cols-3">
    <div class="space-y-6 lg:col-span-2">
        <section class="rounded-2xl border border-stone-200 bg-white p-5">
            <h2 class="font-semibold">Resumen</h2>
            <p class="mt-2 text-sm">{{ $orders->count() }} últimas visitas · Total gastado histórico: <strong>{{ \App\CatalogMoney::format($lifetime) }} €</strong> · Ticket medio: <strong>{{ $orders->count() ? \App\CatalogMoney::format((int) round($lifetime / max(1, $customer->orders()->where('status', 'paid')->count()))) : '—' }} €</strong></p>
            @if ($next)<p class="mt-2 text-sm text-orange-700">Cuenta abierta ahora: venta #{{ $next->id }}</p>@endif
        </section>
        <section class="rounded-2xl border border-stone-200 bg-white p-5">
            <h2 class="font-semibold">Historial de visitas</h2>
            <div class="mt-3 space-y-2 text-sm">@forelse ($orders as $order)<a class="block rounded-xl border border-stone-100 p-3" href="{{ route('restaurant.sales.show', [$restaurant, $order->id]) }}"><span class="font-semibold">#{{ $order->id }}</span> · {{ $order->paid_at?->format('d/m/Y') }} · {{ $order->table?->name ?? $order->channel }} · {{ \App\CatalogMoney::format($order->total_minor) }} €</a>@empty<p class="text-stone-500">Sin visitas cobradas todavía.</p>@endforelse</div>
        </section>
        <section class="rounded-2xl border border-stone-200 bg-white p-5">
            <h2 class="font-semibold">Facturas</h2>
            <div class="mt-3 space-y-2 text-sm">@forelse ($documents as $doc)<a class="block rounded-xl border border-stone-100 p-3" href="{{ route('restaurant.invoices.show', [$restaurant, $doc->id]) }}"><span class="font-semibold">{{ $doc->reference }}</span> · {{ $doc->issued_at?->format('d/m/Y') }} · {{ \App\CatalogMoney::format($doc->total_minor) }} €</a>@empty<p class="text-stone-500">Sin facturas.</p>@endforelse</div>
        </section>
    </div>
    <section class="rounded-2xl border border-stone-200 bg-white p-5">
        <h2 class="font-semibold">Ficha y datos fiscales</h2>
        <p class="mt-1 text-xs text-stone-500">Cambiar estos datos no altera facturas ya emitidas (usan snapshot).</p>
        <form method="POST" action="{{ route('restaurant.customers.update', [$restaurant, $customer->id]) }}" class="mt-3 space-y-3">
            @csrf @method('PATCH')
            <input name="display_name" value="{{ old('display_name', $customer->display_name) }}" placeholder="Nombre" class="form-input">
            <input name="phone" value="{{ old('phone', $customer->phone) }}" placeholder="Teléfono" class="form-input">
            <input name="email" value="{{ old('email', $customer->email) }}" placeholder="Email" class="form-input">
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_company" value="1" @checked($customer->is_company)> Es empresa</label>
            <input name="legal_name" value="{{ old('legal_name', $customer->legal_name) }}" placeholder="Razón social" class="form-input">
            <input name="tax_id" value="{{ old('tax_id', $customer->tax_id) }}" placeholder="NIF/CIF" class="form-input">
            <input name="fiscal_address" value="{{ old('fiscal_address', $customer->fiscal_address) }}" placeholder="Dirección fiscal" class="form-input">
            <div class="grid grid-cols-2 gap-2"><input name="postal_code" value="{{ old('postal_code', $customer->postal_code) }}" placeholder="CP" class="form-input"><input name="city" value="{{ old('city', $customer->city) }}" placeholder="Localidad" class="form-input"></div>
            <textarea name="notes" placeholder="Notas internas" class="form-input">{{ old('notes', $customer->notes) }}</textarea>
            <button class="button-primary w-full">Guardar</button>
        </form>
    </section>
</div>
@endsection
