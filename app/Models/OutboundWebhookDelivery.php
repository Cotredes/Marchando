<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OutboundWebhookDelivery extends Model
{
    protected $fillable = ['outbound_webhook_endpoint_id', 'event', 'payload', 'status', 'attempts', 'response_code', 'next_retry_at'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'attempts' => 'integer', 'response_code' => 'integer', 'next_retry_at' => 'immutable_datetime'];
    }

    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(OutboundWebhookEndpoint::class, 'outbound_webhook_endpoint_id');
    }
}
