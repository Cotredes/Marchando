<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PublicOrderRequest extends Model
{
    protected $fillable = ['restaurant_id', 'dining_table_id', 'accepted_order_id', 'accepted_round_id', 'accepted_by_user_id', 'accepted_by_employee_id', 'coupon_id', 'coupon_code', 'channel', 'status', 'public_token_hash', 'public_token', 'request_key', 'payload_hash', 'currency', 'subtotal_minor', 'delivery_fee_minor', 'total_minor', 'customer_name', 'customer_phone', 'customer_email', 'delivery_address', 'delivery_latitude', 'delivery_longitude', 'distance_meters', 'fulfillment_mode', 'requested_at', 'accepted_at', 'rejected_at', 'cancelled_at', 'rejection_reason', 'version'];

    protected $hidden = ['public_token_hash', 'public_token'];

    protected function casts(): array
    {
        return ['subtotal_minor' => 'integer', 'delivery_fee_minor' => 'integer', 'total_minor' => 'integer', 'distance_meters' => 'integer', 'version' => 'integer', 'public_token' => 'encrypted', 'requested_at' => 'immutable_datetime', 'accepted_at' => 'immutable_datetime', 'rejected_at' => 'immutable_datetime', 'cancelled_at' => 'immutable_datetime'];
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(DiningTable::class, 'dining_table_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'accepted_order_id');
    }

    public function round(): BelongsTo
    {
        return $this->belongsTo(OrderRound::class, 'accepted_round_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PublicOrderRequestLine::class);
    }
}
