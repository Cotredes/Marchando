<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Payment extends Model
{
    protected $fillable = ['restaurant_id', 'order_id', 'order_split_plan_id', 'order_split_part_id', 'cash_session_id', 'user_id', 'employee_id', 'status', 'currency', 'amount_minor', 'request_key', 'reference', 'business_date', 'succeeded_at'];

    protected function casts(): array
    {
        return ['amount_minor' => 'integer', 'business_date' => 'date', 'succeeded_at' => 'immutable_datetime'];
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(OrderSplitPlan::class, 'order_split_plan_id');
    }

    public function part(): BelongsTo
    {
        return $this->belongsTo(OrderSplitPart::class, 'order_split_part_id');
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(CashSession::class, 'cash_session_id');
    }

    public function tenders(): HasMany
    {
        return $this->hasMany(PaymentTender::class);
    }

    public function reversal(): HasOne
    {
        return $this->hasOne(PaymentReversal::class);
    }
}
