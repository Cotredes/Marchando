<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KitchenItem extends Model
{
    protected $fillable = ['restaurant_id', 'kitchen_dispatch_id', 'order_line_id', 'kitchen_station_id', 'station_name', 'product_name', 'format_name', 'quantity', 'cancelled_quantity', 'modifiers', 'notes', 'status', 'version', 'queued_at', 'started_at', 'ready_at', 'served_at'];

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'cancelled_quantity' => 'integer', 'version' => 'integer', 'modifiers' => 'array', 'queued_at' => 'immutable_datetime', 'started_at' => 'immutable_datetime', 'ready_at' => 'immutable_datetime', 'served_at' => 'immutable_datetime'];
    }

    public function dispatch(): BelongsTo
    {
        return $this->belongsTo(KitchenDispatch::class, 'kitchen_dispatch_id');
    }

    public function line(): BelongsTo
    {
        return $this->belongsTo(OrderLine::class, 'order_line_id');
    }

    public function station(): BelongsTo
    {
        return $this->belongsTo(KitchenStation::class, 'kitchen_station_id');
    }

    public function cancellations(): HasMany
    {
        return $this->hasMany(KitchenCancellation::class);
    }

    public function activeQuantity(): int
    {
        return max(0, $this->quantity - $this->cancelled_quantity);
    }
}
