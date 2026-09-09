<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['restaurant_id', 'product_id', 'order_id', 'order_line_id', 'user_id', 'employee_id', 'type', 'quantity_delta', 'resulting_quantity', 'reason'];

    protected function casts(): array
    {
        return ['quantity_delta' => 'integer', 'resulting_quantity' => 'integer', 'created_at' => 'immutable_datetime'];
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
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
