<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderSplitAllocation extends Model
{
    protected $fillable = ['restaurant_id', 'order_split_part_id', 'order_line_id', 'quantity', 'total_minor'];

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'total_minor' => 'integer'];
    }

    public function part(): BelongsTo
    {
        return $this->belongsTo(OrderSplitPart::class, 'order_split_part_id');
    }

    public function line(): BelongsTo
    {
        return $this->belongsTo(OrderLine::class, 'order_line_id');
    }
}
