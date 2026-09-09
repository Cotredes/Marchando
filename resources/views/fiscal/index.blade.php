@extends('layouts.app', ['title' => 'Fiscalidad', 'heading' => 'Fiscalidad VERI*FACTU'])

@section('content')
<div class="flex justify-between gap-4">
    <div>
        <p class="eyebrow">VERI*FACTU · modo {{ $integration->mode === 'live' ? 'producción' : 'pruebas' }}</p>
        <h1 class="mt-1 text-3xl font-semibold">Registros de facturación</h1>
        <p class="mt-2 text-stone-500">Los registros de pruebas nunca se mezclan con los de producción. Sin revisión y declaración responsable del productor no se afirma cumplimiento legal.</p>
    </div>
    <a class="button-secondary" href="{{ route('restaurant.integrations', $restaurant) }}#aeat">Configurar</a>
</div>

@if (session('status'))
    <div class="mt-5 rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
@endif

@if ($errors->any())
    <div class="mt-5 rounded-xl bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
@endif

<div class="mt-6 flex flex-wrap gap-2">
    @foreach (['pending' => 'Pendientes', 'sent' => 'Enviados', 'accepted' => 'Aceptados', 'accepted_with_issues' => 'Con incidencias', 'rejected' => 'Rechazados', 'error' => 'Errores'] as $status => $label)
        <span class="rounded-full border px-4 py-2 text-sm font-semibold {{ ($counts[$status] ?? 0) > 0 && in_array($status, ['rejected', 'error'], true) ? 'border-red-500 bg-red-50 text-red-800' : 'border-stone-200 bg-white' }}">{{ $label }}: {{ $counts[$status] ?? 0 }}</span>
    @endforeach
</div>

<div class="mt-6 space-y-2">
    @forelse ($records as $record)
        <a href="{{ route('restaurant.fiscal.show', [$restaurant, $record]) }}" class="settings-card block p-4 text-sm">
            <p><strong>{{ $record->record_type === 'alta' ? 'Alta' : 'Anulación' }} {{ $record->serie }}/{{ $record->numero }}</strong> · {{ $record->fiscal_type }} · {{ \App\CatalogMoney::format($record->total_minor) }} € · <span class="font-semibold">{{ $record->status }}</span> · {{ $record->environment === 'live' ? 'producción' : 'pruebas' }}</p>
            <p class="text-stone-500">{{ $record->identity->nif }} · {{ $record->issue_date->format('d/m/Y') }} · intentos {{ $record->attempts }}</p>
        </a>
    @empty
        <div class="empty-panel">Sin registros. Se generan automáticamente al emitir facturas y tickets.</div>
    @endforelse
</div>

<div class="mt-4">{{ $records->links() }}</div>
@endsection
