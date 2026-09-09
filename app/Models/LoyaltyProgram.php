<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LoyaltyProgram extends Model
{
    protected $fillable = ['restaurant_id', 'name', 'target_type', 'target_id', 'goal', 'reward_product_id', 'is_active'];

    protected function casts(): array
    {
        return ['target_id' => 'integer', 'goal' => 'integer', 'is_active' => 'boolean'];
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function rewardProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'reward_product_id');
    }

    public function progress(): HasMany
    {
        return $this->hasMany(LoyaltyProgress::class);
    }
}
