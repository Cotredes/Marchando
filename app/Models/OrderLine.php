<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class OrderLine extends Model
{
    protected $fillable = ['restaurant_id', 'order_id', 'order_round_id', 'product_id', 'product_format_id', 'employee_id', 'user_id', 'product_name', 'format_name', 'quantity', 'voided_quantity', 'unit_base_minor', 'unit_modifiers_minor', 'unit_total_minor', 'cost_minor', 'manual_unit_total_minor', 'line_total_minor', 'active_line_total_minor', 'vat_rate', 'currency', 'notes', 'snapshot', 'position'];

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'voided_quantity' => 'integer', 'unit_base_minor' => 'integer', 'unit_modifiers_minor' => 'integer', 'unit_total_minor' => 'integer', 'cost_minor' => 'integer', 'manual_unit_total_minor' => 'integer', 'line_total_minor' => 'integer', 'active_line_total_minor' => 'integer', 'vat_rate' => 'decimal:2', 'snapshot' => 'array', 'position' => 'integer'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function round(): BelongsTo
    {
        return $this->belongsTo(OrderRound::class, 'order_round_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function modifiers(): HasMany
    {
        return $this->hasMany(OrderLineModifier::class)->orderBy('position')->orderBy('id');
    }

    public function activeQuantity(): int
    {
        return max(0, $this->quantity - $this->voided_quantity);
    }

    public function kitchenItem(): HasOne
    {
        return $this->hasOne(KitchenItem::class);
    }
}
