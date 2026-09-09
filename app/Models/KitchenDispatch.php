<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KitchenDispatch extends Model
{
    protected $fillable = ['restaurant_id', 'order_id', 'order_round_id', 'status', 'version', 'fulfillment_label', 'channel', 'submitted_at', 'ready_at', 'room_acknowledged_at', 'room_acknowledged_by_employee_id'];

    protected function casts(): array
    {
        return ['version' => 'integer', 'submitted_at' => 'immutable_datetime', 'ready_at' => 'immutable_datetime', 'room_acknowledged_at' => 'immutable_datetime'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function round(): BelongsTo
    {
        return $this->belongsTo(OrderRound::class, 'order_round_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(KitchenItem::class);
    }
}
