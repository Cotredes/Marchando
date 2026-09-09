@extends('layouts.app', ['title' => 'Cupones', 'heading' => 'Cupones'])

@section('content')
<div class="flex justify-between gap-4">
    <div>
        <p class="eyebrow">Promociones</p>
        <h1 class="mt-1 text-3xl font-semibold">Cupones</h1>
        <p class="mt-2 text-stone-500">Validados siempre en servidor. No acumulables con otros descuentos. El descuento integra fiscalidad y auditoría.</p>
    </div>
    <a class="button-secondary" href="{{ route('restaurant.integrations', $restaurant) }}">Centro</a>
</div>

@if (session('status'))
    <div class="mt-5 rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
@endif

@if ($errors->any())
    <div class="mt-5 rounded-xl bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
@endif

<div class="mt-8 grid gap-6 lg:grid-cols-[1fr_320px]">
    <section class="space-y-3">
        @forelse ($coupons as $coupon)
            <article class="settings-card p-5">
                <div class="flex flex-wrap justify-between gap-3">
                    <div>
                        <h2 class="font-semibold">{{ $coupon->code }} <span class="text-sm font-normal text-stone-500">{{ $coupon->name }}</span></h2>
                        <p class="text-sm text-stone-500">{{ $coupon->kind === 'percent' ? ($coupon->value / 100).' %' : \App\CatalogMoney::format($coupon->value).' €' }} · {{ $coupon->is_active ? 'activo' : 'inactivo' }} · usos {{ $coupon->uses_count }}{{ $coupon->max_uses ? '/'.$coupon->max_uses : '' }} · {{ $coupon->redemptions_count }} canjes</p>
                    </div>
                    <form method="POST" action="{{ route('restaurant.coupons.update', [$restaurant, $coupon]) }}">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="code" value="{{ $coupon->code }}">
                        <input type="hidden" name="kind" value="{{ $coupon->kind }}">
                        <input type="hidden" name="value" value="{{ $coupon->value }}">
                        <button class="button-secondary" name="is_active" value="{{ $coupon->is_active ? 0 : 1 }}">{{ $coupon->is_active ? 'Desactivar' : 'Activar' }}</button>
                    </form>
                </div>
            </article>
        @empty
            <div class="empty-panel">Sin cupones. Crea VERANO10 para empezar.</div>
        @endforelse
    </section>
    <section class="settings-card">
        <form method="POST" action="{{ route('restaurant.coupons.store', $restaurant) }}">
            @csrf
            <div class="settings-card-body space-y-4">
                <h2 class="font-semibold">Nuevo cupón</h2>
                <input class="form-input" name="code" placeholder="Código (VERANO10)" required>
                <input class="form-input" name="name" placeholder="Nombre interno">
                <div class="grid grid-cols-2 gap-3">
                    <select class="form-input" name="kind">
                        <option value="percent">Porcentaje (puntos básicos)</option>
                        <option value="fixed">Importe fijo (céntimos)</option>
                    </select>
                    <input class="form-input" type="number" name="value" min="1" placeholder="1000 = 10 % ó céntimos" required>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <input class="form-input" type="datetime-local" name="starts_at">
                    <input class="form-input" type="datetime-local" name="ends_at">
                </div>
                <input class="form-input" type="number" name="min_order_minor" min="0" placeholder="Pedido mínimo (céntimos)">
                <input class="form-input" type="number" name="max_uses" min="1" placeholder="Usos máximos (opcional)">
                <label class="check-label"><input type="checkbox" name="one_per_customer" value="1"> Un uso por cliente</label>
                <button class="button-primary w-full">Crear cupón</button>
            </div>
        </form>
    </section>
</div>
@endsection
