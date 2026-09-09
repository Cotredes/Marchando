<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KitchenCancellation extends Model
{
    protected $fillable = ['restaurant_id', 'kitchen_item_id', 'order_event_id', 'quantity', 'reason', 'status', 'acknowledged_by_employee_id', 'raised_at', 'acknowledged_at'];

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'raised_at' => 'immutable_datetime', 'acknowledged_at' => 'immutable_datetime'];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(KitchenItem::class, 'kitchen_item_id');
    }
}
