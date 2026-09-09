<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OnlinePaymentIntent extends Model
{
    protected $fillable = ['restaurant_id', 'public_order_request_id', 'order_id', 'customer_id', 'channel', 'provider', 'mode', 'amount_minor', 'currency', 'provider_intent_id', 'client_secret', 'checkout_url', 'status', 'failure_reason', 'request_key'];

    protected function casts(): array
    {
        return ['amount_minor' => 'integer', 'client_secret' => 'encrypted'];
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function publicRequest(): BelongsTo
    {
        return $this->belongsTo(PublicOrderRequest::class, 'public_order_request_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(OnlineRefund::class);
    }

    public function refundedMinor(): int
    {
        return (int) $this->refunds()->where('status', 'succeeded')->sum('amount_minor');
    }
}
