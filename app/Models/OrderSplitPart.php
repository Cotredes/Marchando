<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderSplitPart extends Model
{
    protected $fillable = ['restaurant_id', 'order_split_plan_id', 'sequence', 'label', 'total_minor', 'status', 'version'];

    protected function casts(): array
    {
        return ['sequence' => 'integer', 'total_minor' => 'integer', 'version' => 'integer'];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(OrderSplitPlan::class, 'order_split_plan_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(OrderSplitAllocation::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
