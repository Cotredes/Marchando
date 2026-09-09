@extends('layouts.app', ['title' => 'Auditoría', 'heading' => 'Libro de actividad'])

@section('content')
@include('analytics.nav')
<p class="mb-4 text-sm text-stone-500">Trazabilidad de operaciones sensibles en lenguaje de negocio. No editable ni eliminable.</p>
<form method="GET" class="mb-5 grid gap-3 rounded-2xl border border-stone-200 bg-white p-4 sm:grid-cols-2 lg:grid-cols-5">
    <div><label class="text-xs font-semibold text-stone-500">Desde</label><input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="form-input mt-1"></div>
    <div><label class="text-xs font-semibold text-stone-500">Hasta</label><input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="form-input mt-1"></div>
    <div><label class="text-xs font-semibold text-stone-500">Empleado</label><select name="employee_id" class="form-input mt-1"><option value="">Todos</option>@foreach ($employees as $employee)<option value="{{ $employee->id }}" @selected(($filters['employee_id'] ?? '') == $employee->id)>{{ $employee->display_name }}</option>@endforeach</select></div>
    <div><label class="text-xs font-semibold text-stone-500">Módulo</label><select name="module" class="form-input mt-1"><option value="">Todos</option>@foreach (['ventas' => 'Ventas', 'caja' => 'Caja', 'stock' => 'Stock', 'personal' => 'Personal'] as $key => $label)<option value="{{ $key }}" @selected(($filters['module'] ?? '') === $key)>{{ $label }}</option>@endforeach</select></div>
    <div class="flex items-end"><button class="button-primary">Filtrar</button></div>
</form>
<div class="space-y-2">
    @forelse ($entries as $entry)
        <div class="rounded-2xl border border-stone-200 bg-white p-4 text-sm">
            <p><strong>{{ $entry['actor'] }}</strong> {{ $entry['action'] }} <span class="ml-1 rounded bg-stone-100 px-2 py-0.5 text-xs">{{ $entry['module'] }}</span></p>
            <p class="mt-1 text-stone-500">{{ $entry['at']?->format('d/m/Y H:i') }}@if ($entry['detail']) · {{ $entry['detail'] }}@endif @if ($entry['order_id'])· <a class="text-orange-700" href="{{ route('restaurant.sales.show', [$restaurant, $entry['order_id']]) }}">venta #{{ $entry['order_id'] }}</a>@endif</p>
        </div>
    @empty
        <div class="rounded-2xl border border-dashed border-stone-300 bg-white p-10 text-center text-stone-500">Sin actividad para esos filtros.</div>
    @endforelse
</div>
@endsection
