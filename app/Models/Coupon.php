<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Coupon extends Model
{
    protected $fillable = ['restaurant_id', 'code', 'name', 'kind', 'value', 'starts_at', 'ends_at', 'is_active', 'channels', 'min_order_minor', 'max_uses', 'uses_count', 'one_per_customer'];

    protected function casts(): array
    {
        return ['value' => 'integer', 'is_active' => 'boolean', 'channels' => 'array', 'min_order_minor' => 'integer', 'max_uses' => 'integer', 'uses_count' => 'integer', 'one_per_customer' => 'boolean', 'starts_at' => 'immutable_datetime', 'ends_at' => 'immutable_datetime'];
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(CouponRedemption::class);
    }
}
