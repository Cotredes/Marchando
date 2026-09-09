@extends('layouts.app', ['title' => 'Clientes', 'heading' => 'Clientes'])

@section('content')
@include('analytics.nav')
@if (session('status'))<div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>@endif
@if ($errors->any())<div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>@endif
<div class="grid gap-6 lg:grid-cols-3">
    <section class="rounded-2xl border border-stone-200 bg-white p-5 lg:col-span-2">
        <form method="GET" class="mb-4 flex gap-2"><input name="q" value="{{ request('q') }}" placeholder="Nombre, teléfono, email o NIF" class="form-input"><button class="button-secondary">Buscar</button></form>
        <div class="space-y-2">@forelse ($customers as $customer)<a href="{{ route('restaurant.customers.show', [$restaurant, $customer->id]) }}" class="block rounded-xl border border-stone-100 p-3"><p class="font-semibold">{{ $customer->display_name ?? 'Sin nombre' }} @if ($customer->tax_id)<span class="text-xs text-stone-400">· {{ $customer->tax_id }}</span>@endif</p><p class="text-sm text-stone-500">{{ $customer->phone ?? '—' }} · {{ $customer->orders_count }} visitas · {{ $customer->sale_documents_count }} facturas</p></a>@empty<p class="text-sm text-stone-500">Sin clientes. Se crean al cobrar ventas identificadas, desde reservas/Take Away/Delivery o manualmente aquí.</p>@endforelse</div>
        <div class="mt-4">{{ $customers->links() }}</div>
    </section>
    <section class="rounded-2xl border border-stone-200 bg-white p-5">
        <h2 class="font-semibold">Nuevo cliente</h2>
        <form method="POST" action="{{ route('restaurant.customers.store', $restaurant) }}" class="mt-3 space-y-3">
            @csrf
            <input name="display_name" placeholder="Nombre" class="form-input">
            <input name="phone" placeholder="Teléfono" class="form-input">
            <input name="email" type="email" placeholder="Email" class="form-input">
            <input name="tax_id" placeholder="NIF/CIF (solo si factura)" class="form-input">
            <input name="fiscal_address" placeholder="Dirección fiscal" class="form-input">
            <div class="grid grid-cols-2 gap-2"><input name="postal_code" placeholder="CP" class="form-input"><input name="city" placeholder="Localidad" class="form-input"></div>
            <button class="button-primary w-full">Crear</button>
        </form>
    </section>
</div>
@endsection
