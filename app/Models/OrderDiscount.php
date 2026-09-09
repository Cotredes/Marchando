<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderDiscount extends Model
{
    protected $fillable = ['restaurant_id', 'order_id', 'user_id', 'employee_id', 'coupon_id', 'source', 'kind', 'percentage_basis_points', 'fixed_minor', 'subtotal_minor', 'discount_minor', 'total_minor', 'reason', 'is_active'];

    protected function casts(): array
    {
        return ['percentage_basis_points' => 'integer', 'fixed_minor' => 'integer', 'subtotal_minor' => 'integer', 'discount_minor' => 'integer', 'total_minor' => 'integer', 'is_active' => 'boolean'];
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
