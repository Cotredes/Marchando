<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['restaurant_id', 'order_id', 'user_id', 'employee_id', 'type', 'data'];

    protected function casts(): array
    {
        return ['data' => 'array', 'created_at' => 'immutable_datetime'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
