<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderFulfillment extends Model
{
    protected $fillable = ['restaurant_id', 'order_id', 'channel', 'status', 'fulfillment_mode', 'customer_name', 'customer_phone', 'customer_email', 'delivery_address', 'delivery_latitude', 'delivery_longitude', 'distance_meters', 'delivery_fee_minor', 'requested_at', 'estimated_ready_at', 'dispatched_at', 'completed_at', 'delivery_employee_id'];

    protected function casts(): array
    {
        return ['distance_meters' => 'integer', 'delivery_fee_minor' => 'integer', 'requested_at' => 'immutable_datetime', 'estimated_ready_at' => 'immutable_datetime', 'dispatched_at' => 'immutable_datetime', 'completed_at' => 'immutable_datetime'];
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function deliveryEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'delivery_employee_id');
    }
}
