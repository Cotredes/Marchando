<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActiveTableOrder extends Model
{
    protected $table = 'active_table_orders';

    public $incrementing = false;

    protected $fillable = ['restaurant_id', 'dining_table_id', 'order_id'];

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
        return $this->belongsTo(Order::class);
    }
}
