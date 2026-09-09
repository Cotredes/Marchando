<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OutboundWebhookEndpoint extends Model
{
    public const EVENTS = ['order.created', 'order.accepted', 'order.completed', 'payment.completed', 'invoice.created', 'reservation.created'];

    protected $fillable = ['restaurant_id', 'url', 'signing_secret', 'events', 'is_active'];

    protected function casts(): array
    {
        return ['events' => 'array', 'is_active' => 'boolean', 'signing_secret' => 'encrypted'];
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(OutboundWebhookDelivery::class);
    }

    public function listens(string $event): bool
    {
        $events = $this->events ?? [];

        return $this->is_active && ($events === [] || in_array($event, $events, true));
    }
}
