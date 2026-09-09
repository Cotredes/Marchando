<?php

namespace App;

use App\Models\PrintConnector;
use App\Models\PrintJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ConnectorService
{
    public const LEASE_SECONDS = 120;

    public const PAIRING_TTL_MINUTES = 15;

    public function create($restaurant, string $name): array
    {
        $apiToken = 'mch_'.bin2hex(random_bytes(24));
        $pairingCode = strtoupper(Str::random(8));
        $connector = $restaurant->printConnectors()->create([
            'name' => $name, 'api_token_hash' => hash('sha256', $apiToken),
            'pairing_code_hash' => hash('sha256', $pairingCode),
            'pairing_expires_at' => now()->addMinutes(self::PAIRING_TTL_MINUTES), 'status' => 'pending',
        ]);

        return [$connector->fresh(), $apiToken, $pairingCode];
    }

    public function link(string $pairingCode, ?string $agentVersion = null): PrintConnector
    {
        return DB::transaction(function () use ($pairingCode, $agentVersion): PrintConnector {
            $connector = PrintConnector::query()->lockForUpdate()->where('pairing_code_hash', hash('sha256', strtoupper(trim($pairingCode))))->first();
            if (! $connector || ! $connector->pairing_expires_at || $connector->pairing_expires_at->isPast() || ! $connector->is_active) {
                throw new InvalidArgumentException('Código de vinculación no válido o caducado.');
            }
            $connector->update(['status' => 'linked', 'pairing_code_hash' => null, 'pairing_expires_at' => null, 'agent_version' => $agentVersion ? mb_substr($agentVersion, 0, 40) : null, 'last_heartbeat_at' => now()]);

            return $connector->fresh();
        });
    }

    public function authenticate(string $apiToken): PrintConnector
    {
        $connector = PrintConnector::query()->where('api_token_hash', hash('sha256', $apiToken))->where('is_active', true)->first();
        abort_unless($connector && $connector->status === 'linked', 401, 'Conector no autorizado.');

        return $connector;
    }

    public function heartbeat(PrintConnector $connector, ?string $agentVersion = null): PrintConnector
    {
        $connector->update(['last_heartbeat_at' => now(), 'agent_version' => $agentVersion ? mb_substr($agentVersion, 0, 40) : $connector->agent_version]);

        return $connector->fresh();
    }

    public function pendingJobs(PrintConnector $connector, int $limit = 10)
    {
        return DB::transaction(function () use ($connector, $limit) {
            $jobs = PrintJob::query()->where('restaurant_id', $connector->restaurant_id)->where('status', 'pending')
                ->where('attempts', '<', PrintService::MAX_ATTEMPTS)
                ->orderBy('id')->limit($limit)->lockForUpdate()->get();
            foreach ($jobs as $job) {
                $job->update(['status' => 'sending', 'claimed_at' => now(), 'print_connector_id' => $connector->id]);
            }

            return $jobs->fresh();
        });
    }

    public function acknowledge(PrintConnector $connector, PrintJob $job, bool $printed, ?string $error = null): PrintJob
    {
        abort_unless($job->restaurant_id === $connector->restaurant_id && $job->print_connector_id === $connector->id && $job->status === 'sending', 404);
        if ($printed) {
            return app(PrintService::class)->markPrinted($job);
        }

        return app(PrintService::class)->markError($job, $error ?: 'Error del conector');
    }

    public function releaseStaleLeases(): int
    {
        return PrintJob::query()->where('status', 'sending')
            ->where('claimed_at', '<', now()->subSeconds(self::LEASE_SECONDS))
            ->update(['status' => 'pending', 'claimed_at' => null, 'print_connector_id' => null]);
    }
}
