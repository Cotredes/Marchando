<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderLineModifier extends Model
{
    protected $fillable = ['restaurant_id', 'order_line_id', 'product_modifier_group_id', 'modifier_group_id', 'modifier_option_id', 'group_name', 'option_name', 'instruction', 'quantity', 'unit_delta_minor', 'total_delta_minor', 'position'];

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'unit_delta_minor' => 'integer', 'total_delta_minor' => 'integer', 'position' => 'integer'];
    }

    public function line(): BelongsTo
    {
        return $this->belongsTo(OrderLine::class, 'order_line_id');
    }
}
