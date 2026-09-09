<?php

namespace App\Fiscal;

use App\Models\FiscalRecord;

class FakeAeatTransport implements AeatTransport
{
    public function send(FiscalRecord $record): array
    {
        $settings = $record->restaurant->integrations()->where('provider', 'aeat')->first()?->settings ?? [];
        $simulate = $settings['simulate'] ?? 'ok';
        if ($simulate === 'offline') {
            return ['status' => 'error', 'code' => 'NETWORK', 'body' => 'Servicio AEAT no disponible (simulación).'];
        }
        if ($simulate === 'reject_once' && $record->attempts === 0) {
            return ['status' => 'rejected', 'code' => '1104', 'body' => 'Rechazado por validación (simulación de incidencia).'];
        }

        return ['status' => 'accepted', 'code' => 'OK', 'body' => 'Registro aceptado en el entorno de pruebas (simulación).'];
    }
}
