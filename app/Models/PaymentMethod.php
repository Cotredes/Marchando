<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentMethod extends Model
{
    protected $fillable = ['restaurant_id', 'code', 'name', 'is_cash', 'is_active', 'position'];

    protected function casts(): array
    {
        return ['is_cash' => 'boolean', 'is_active' => 'boolean', 'position' => 'integer'];
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function tenders(): HasMany
    {
        return $this->hasMany(PaymentTender::class);
    }
}
