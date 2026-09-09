<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProviderWebhookEvent extends Model
{
    protected $fillable = ['restaurant_id', 'provider', 'provider_event_id', 'type', 'payload', 'status', 'processed_at'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'processed_at' => 'immutable_datetime'];
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }
}
