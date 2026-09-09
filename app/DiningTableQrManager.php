<?php

namespace App;

use App\Models\DiningTable;
use Illuminate\Support\Facades\DB;

class DiningTableQrManager
{
    public function activate(DiningTable $table): DiningTable
    {
        $table->update(['qr_is_active' => true, 'qr_activated_at' => now(), 'qr_revoked_at' => null]);

        return $table->refresh();
    }

    public function revoke(DiningTable $table): DiningTable
    {
        $table->update(['qr_is_active' => false, 'qr_revoked_at' => now()]);

        return $table->refresh();
    }

    public function regenerate(DiningTable $table): DiningTable
    {
        return DB::transaction(function () use ($table): DiningTable {
            for ($attempt = 0; $attempt < 3; $attempt++) {
                $token = bin2hex(random_bytes(32));
                if (! DiningTable::query()->where('qr_token', $token)->exists()) {
                    $table->forceFill(['qr_token' => $token, 'qr_revoked_at' => now()])->save();

                    return $table->refresh();
                }
            }

            throw new \RuntimeException('No se pudo generar un token QR único.');
        });
    }

    public function newToken(): string
    {
        return bin2hex(random_bytes(32));
    }
}
