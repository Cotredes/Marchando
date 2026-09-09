<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PrintConnector extends Model
{
    protected $fillable = ['restaurant_id', 'name', 'api_token_hash', 'pairing_code_hash', 'pairing_expires_at', 'status', 'last_heartbeat_at', 'agent_version', 'is_active'];

    protected $hidden = ['api_token_hash', 'pairing_code_hash'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'pairing_expires_at' => 'immutable_datetime', 'last_heartbeat_at' => 'immutable_datetime'];
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function jobs(): HasMany
    {
        return $this->hasMany(PrintJob::class);
    }

    public function isOnline(int $seconds = 90): bool
    {
        return $this->status === 'linked' && $this->is_active && $this->last_heartbeat_at && $this->last_heartbeat_at->greaterThan(now()->subSeconds($seconds));
    }
}
