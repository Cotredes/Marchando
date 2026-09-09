<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderSplitPlan extends Model
{
    protected $fillable = ['restaurant_id', 'order_id', 'user_id', 'employee_id', 'method', 'status', 'source_total_minor', 'source_order_version', 'version', 'request_key'];

    protected function casts(): array
    {
        return ['source_total_minor' => 'integer', 'source_order_version' => 'integer', 'version' => 'integer'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function parts(): HasMany
    {
        return $this->hasMany(OrderSplitPart::class)->orderBy('sequence');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
