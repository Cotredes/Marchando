<?php

namespace App;

use App\Fiscal\AeatTransport;
use App\Fiscal\FakeAeatTransport;
use App\Models\FiscalIdentity;
use App\Models\FiscalRecord;
use App\Models\Integration;
use App\Models\SaleDocument;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class FiscalService
{
    public const SOFTWARE_VERSION_FALLBACK = '14.0.0-dev';

    public static function softwareVersion(): string
    {
        return (string) config('app.marchando_version', self::SOFTWARE_VERSION_FALLBACK);
    }

    public const QR_PROD_BASE = 'https://www2.agenciatributaria.gob.es/wlpl/TIKE-CONT/ValidarQR';

    public const QR_TEST_BASE = 'https://prewww2.aeat.es/wlpl/TIKE-CONT/ValidarQR';

    public function integration($restaurant): Integration
    {
        return Integration::query()->firstOrCreate(
            ['restaurant_id' => $restaurant->id, 'provider' => 'aeat'],
            ['status' => 'not_configured', 'mode' => 'test']
        );
    }

    public function environment($restaurant): string
    {
        return $this->integration($restaurant)->mode === 'live' ? 'live' : 'test';
    }

    public function defaultIdentity($restaurant): ?FiscalIdentity
    {
        $identity = $restaurant->fiscalIdentities()->where('is_default', true)->first()
            ?? $restaurant->fiscalIdentities()->first();
        if (! $identity && filled($restaurant->tax_id)) {
            $identity = $restaurant->fiscalIdentities()->create(['nif' => mb_strtoupper(trim($restaurant->tax_id)), 'legal_name' => $restaurant->legal_name ?: $restaurant->name, 'is_default' => true]);
        }

        return $identity && filled($identity->nif) ? $identity : null;
    }

    public function transport($restaurant): AeatTransport
    {
        return new FakeAeatTransport;
    }

    public function registerForDocument(SaleDocument $document): ?FiscalRecord
    {
        $document->loadMissing(['restaurant', 'customer']);
        $restaurant = $document->restaurant;
        $identity = $this->defaultIdentity($restaurant);
        if (! $identity) {
            return null;
        }
        $environment = $this->environment($restaurant);
        $fiscalType = $document->kind === 'invoice' ? 'F1' : 'F2';
        $year = $document->issued_at?->setTimezone($restaurant->timezone)->year ?? now($restaurant->timezone)->year;
        $serie = ($document->kind === 'invoice' ? 'F' : 'T').$year;
        $numero = str_pad((string) $document->number, 6, '0', STR_PAD_LEFT);

        return DB::transaction(function () use ($document, $restaurant, $identity, $environment, $fiscalType, $serie, $numero): ?FiscalRecord {
            $existing = FiscalRecord::query()->where('sale_document_id', $document->id)->where('record_type', 'alta')->where('environment', $environment)->first();
            if ($existing) {
                return $existing;
            }
            $previous = FiscalRecord::query()->where('restaurant_id', $restaurant->id)->where('fiscal_identity_id', $identity->id)->where('environment', $environment)->latest('id')->lockForUpdate()->first();
            $previousHash = $previous?->hash;
            $issueDate = $document->issued_at?->setTimezone($restaurant->timezone) ?? now($restaurant->timezone);
            $generatedAt = CarbonImmutable::now($restaurant->timezone);
            $taxTotal = collect($document->tax_breakdown ?? [])->sum('tax_minor');
            $hash = $this->altaHash($identity->nif, $serie.'/'.$numero, $issueDate->format('d-m-Y'), $fiscalType, $taxTotal, $document->total_minor, $previousHash, $generatedAt->format('Y-m-d\TH:i:sP'));
            $record = FiscalRecord::create([
                'restaurant_id' => $restaurant->id, 'fiscal_identity_id' => $identity->id, 'sale_document_id' => $document->id,
                'environment' => $environment, 'record_type' => 'alta', 'fiscal_type' => $fiscalType,
                'serie' => $serie, 'numero' => $numero, 'issue_date' => $issueDate->toDateString(),
                'total_minor' => $document->total_minor, 'tax_total_minor' => $taxTotal,
                'previous_hash' => $previousHash, 'hash' => $hash,
                'qr_content' => $this->qrContent($environment, $identity->nif, $serie.'/'.$numero, $issueDate->format('d-m-Y'), $document->total_minor),
                'software_version' => self::softwareVersion(), 'status' => 'pending',
            ]);
            DB::afterCommit(fn () => $this->send($record->fresh()));

            return $record;
        });
    }

    public function anular(FiscalRecord $alta, $user, ?string $reason = null): FiscalRecord
    {
        abort_unless($alta->record_type === 'alta', 422, 'Solo se puede anular un registro de alta.');
        $restaurant = $alta->restaurant;
        $identity = $alta->identity;

        return DB::transaction(function () use ($alta, $restaurant, $identity): FiscalRecord {
            $existing = FiscalRecord::query()->where('sale_document_id', $alta->sale_document_id)->where('record_type', 'anulacion')->first();
            if ($existing) {
                return $existing;
            }
            $previous = FiscalRecord::query()->where('restaurant_id', $restaurant->id)->where('fiscal_identity_id', $identity->id)->where('environment', $alta->environment)->latest('id')->lockForUpdate()->first();
            $generatedAt = CarbonImmutable::now($restaurant->timezone);
            $hash = $this->anulacionHash($identity->nif, $alta->serie.'/'.$alta->numero, $alta->issue_date->format('d-m-Y'), $previous?->hash, $generatedAt->format('Y-m-d\TH:i:sP'));
            $record = FiscalRecord::create([
                'restaurant_id' => $restaurant->id, 'fiscal_identity_id' => $identity->id, 'sale_document_id' => $alta->sale_document_id,
                'environment' => $alta->environment, 'record_type' => 'anulacion', 'fiscal_type' => $alta->fiscal_type,
                'serie' => $alta->serie, 'numero' => $alta->numero, 'issue_date' => $alta->issue_date,
                'total_minor' => $alta->total_minor, 'tax_total_minor' => $alta->tax_total_minor,
                'previous_hash' => $previous?->hash, 'hash' => $hash,
                'qr_content' => $alta->qr_content, 'software_version' => self::softwareVersion(), 'status' => 'pending',
            ]);
            DB::afterCommit(fn () => $this->send($record->fresh()));

            return $record;
        });
    }

    public function send(FiscalRecord $incoming): FiscalRecord
    {
        return DB::transaction(function () use ($incoming): FiscalRecord {
            $record = FiscalRecord::query()->lockForUpdate()->findOrFail($incoming->id);
            if (in_array($record->status, ['sent', 'accepted'], true)) {
                return $record;
            }
            $record->increment('attempts');
            try {
                $result = $this->transport($record->restaurant)->send($record->fresh());
            } catch (\Throwable $exception) {
                $result = ['status' => 'error', 'code' => 'TRANSPORT', 'body' => mb_substr($exception->getMessage(), 0, 1000)];
            }
            $record->tries()->create(['status' => $result['status'], 'response_code' => $result['code'], 'response_body' => $result['body']]);
            $record->update(['status' => $result['status'], 'aeat_response' => ['code' => $result['code'], 'body' => $result['body']], 'sent_at' => now()]);

            return $record->fresh();
        });
    }

    public function retry(FiscalRecord $record): FiscalRecord
    {
        abort_unless(in_array($record->status, ['pending', 'rejected', 'error'], true), 422, 'Este registro no admite reintento.');

        return $this->send($record);
    }

    public function altaHash(string $nif, string $numserie, string $fecha, string $tipo, int $cuotaMinor, int $totalMinor, ?string $previousHash, string $generatedAt): string
    {
        $chain = 'IDEmisorFactura='.$nif.'&NumSerieFactura='.$numserie.'&FechaExpedicionFactura='.$fecha.'&TipoFactura='.$tipo
            .'&CuotaTotal='.$this->money($cuotaMinor).'&ImporteTotal='.$this->money($totalMinor)
            .'&Huella='.($previousHash ?? '').'&FechaHoraHusoGenRegistro='.$generatedAt;

        return strtoupper(hash('sha256', $chain));
    }

    public function anulacionHash(string $nif, string $numserie, string $fecha, ?string $previousHash, string $generatedAt): string
    {
        $chain = 'IDEmisorFacturaAnulada='.$nif.'&NumSerieFacturaAnulada='.$numserie.'&FechaExpedicionFacturaAnulada='.$fecha
            .'&Huella='.($previousHash ?? '').'&FechaHoraHusoGenRegistro='.$generatedAt;

        return strtoupper(hash('sha256', $chain));
    }

    public function qrContent(string $environment, string $nif, string $numserie, string $fecha, int $totalMinor): string
    {
        $base = $environment === 'live' ? self::QR_PROD_BASE : self::QR_TEST_BASE;
        $query = http_build_query(['nif' => $nif, 'numserie' => $numserie, 'fecha' => $fecha, 'importe' => $this->money($totalMinor)], '', '&', PHP_QUERY_RFC3986);

        return $base.'?'.$query;
    }

    private function money(int $minor): string
    {
        return number_format($minor / 100, 2, '.', '');
    }
}
