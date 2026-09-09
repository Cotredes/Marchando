<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformAudit extends Model
{
    protected $fillable = [
        'user_id',
        'restaurant_id',
        'action',
        'detail',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    /**
     * Registro append-only de acciones relevantes del propietario global.
     * Nunca se actualiza ni se elimina desde la aplicación.
     */
    public static function record(User $actor, string $action, ?Restaurant $restaurant = null, ?string $detail = null): self
    {
        return self::query()->create([
            'user_id' => $actor->getKey(),
            'restaurant_id' => $restaurant?->getKey(),
            'action' => $action,
            'detail' => $detail,
        ]);
    }
}
