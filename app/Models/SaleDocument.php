<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SaleDocument extends Model
{
    protected $fillable = ['restaurant_id', 'order_id', 'customer_id', 'issued_by_user_id', 'issued_by_employee_id', 'kind', 'number', 'reference', 'currency', 'subtotal_minor', 'discount_minor', 'charges_minor', 'total_minor', 'tax_breakdown', 'snapshot', 'status', 'issued_at'];

    protected function casts(): array
    {
        return ['number' => 'integer', 'subtotal_minor' => 'integer', 'discount_minor' => 'integer', 'charges_minor' => 'integer', 'total_minor' => 'integer', 'tax_breakdown' => 'array', 'snapshot' => 'array', 'issued_at' => 'immutable_datetime'];
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function fiscalRecords(): HasMany
    {
        return $this->hasMany(FiscalRecord::class);
    }
}
