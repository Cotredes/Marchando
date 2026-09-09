<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Integration extends Model
{
    public const PROVIDERS = ['stripe', 'aeat', 'accounting'];

    protected $fillable = ['restaurant_id', 'provider', 'status', 'mode', 'settings', 'secrets', 'last_check_at', 'last_error'];

    protected function casts(): array
    {
        return ['settings' => 'array', 'secrets' => 'encrypted:array', 'last_check_at' => 'immutable_datetime'];
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function secret(string $key, ?string $default = null): ?string
    {
        $value = ($this->secrets ?? [])[$key] ?? null;

        return filled($value) ? (string) $value : $default;
    }
}
