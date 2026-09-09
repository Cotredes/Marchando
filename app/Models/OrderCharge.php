<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderCharge extends Model
{
    protected $fillable = ['restaurant_id', 'order_id', 'type', 'label', 'amount_minor', 'currency'];

    protected function casts(): array
    {
        return ['amount_minor' => 'integer'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
