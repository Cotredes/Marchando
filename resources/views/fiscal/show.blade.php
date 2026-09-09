@extends('layouts.app', ['title' => 'Registro fiscal', 'heading' => 'Registro fiscal'])

@section('content')
<a class="text-button" href="{{ route('restaurant.fiscal', $restaurant) }}">← Panel fiscal</a>

<div class="mt-4">
    <p class="eyebrow">{{ $record->record_type === 'alta' ? 'Alta' : 'Anulación' }} · {{ $record->environment === 'live' ? 'producción' : 'pruebas' }}</p>
    <h1 class="mt-1 text-3xl font-semibold">{{ $record->serie }}/{{ $record->numero }}</h1>
</div>

@if (session('status'))
    <div class="mt-5 rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
@endif

@if ($errors->any())
    <div class="mt-5 rounded-xl bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
@endif

<div class="mt-6 grid gap-6 lg:grid-cols-2">
    <section class="settings-card p-5 text-sm">
        <h2 class="font-semibold">Registro</h2>
        <p class="mt-2">Obligado: {{ $record->identity->nif }} · {{ $record->identity->legal_name }}</p>
        <p>Tipo: {{ $record->fiscal_type }} · Fecha: {{ $record->issue_date->format('d/m/Y') }} · Total: {{ \App\CatalogMoney::format($record->total_minor) }} €</p>
        <p>Estado: <strong>{{ $record->status }}</strong> · Intentos: {{ $record->attempts }} · Versión SIF: {{ $record->software_version }}</p>
        <p class="mt-2 break-all">Huella: <code>{{ $record->hash }}</code></p>
        <p class="break-all">Anterior: <code>{{ $record->previous_hash ?? '— (primer registro)' }}</code></p>
        @if ($record->aeat_response)
            <p class="mt-2">Respuesta: {{ $record->aeat_response['code'] ?? '' }} · {{ $record->aeat_response['body'] ?? '' }}</p>
        @endif
        <div class="mt-4 flex flex-wrap gap-2">
            @if (in_array($record->status, ['pending', 'rejected', 'error'], true))
                <form method="POST" action="{{ route('restaurant.fiscal.retry', [$restaurant, $record]) }}">
                    @csrf
                    <button class="button-primary">Reintentar envío</button>
                </form>
            @endif
            @if ($record->record_type === 'alta')
                <form method="POST" action="{{ route('restaurant.fiscal.anular', [$restaurant, $record]) }}" class="flex gap-2" onsubmit="return confirm('¿Generar registro de anulación? El alta original se conserva.')">
                    @csrf
                    <input class="form-input" name="reason" placeholder="Motivo (opcional)">
                    <button class="button-secondary">Anular fiscalmente</button>
                </form>
            @endif
        </div>
    </section>
    <section class="settings-card p-5 text-sm">
        <h2 class="font-semibold">QR tributario</h2>
        <p class="mt-1 text-stone-500">No confundir con el QR de mesa. Tamaño 30–40 mm, nivel M.</p>
        <img class="mt-3 border p-2" src="{{ route('restaurant.fiscal.qr', [$restaurant, $record]) }}" alt="QR tributario" width="240">
        <p class="mt-2 break-all text-xs">{{ $record->qr_content }}</p>
        <h2 class="mt-5 font-semibold">Intentos</h2>
        @forelse ($record->tries as $attempt)
            <p class="mt-1">{{ $attempt->created_at->format('d/m H:i') }} · {{ $attempt->status }} · {{ $attempt->response_code }} · {{ $attempt->response_body }}</p>
        @empty
            <p class="text-stone-500">Sin intentos.</p>
        @endforelse
    </section>
</div>
@endsection
