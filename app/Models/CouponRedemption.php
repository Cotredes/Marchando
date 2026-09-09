<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CouponRedemption extends Model
{
    protected $fillable = ['coupon_id', 'restaurant_id', 'order_id', 'customer_id', 'customer_fp', 'amount_minor'];

    protected function casts(): array
    {
        return ['amount_minor' => 'integer'];
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
