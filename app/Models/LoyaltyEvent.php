<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoyaltyEvent extends Model
{
    protected $fillable = ['restaurant_id', 'loyalty_program_id', 'customer_id', 'order_id', 'kind', 'quantity', 'user_id', 'employee_id'];

    protected function casts(): array
    {
        return ['quantity' => 'integer'];
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(LoyaltyProgram::class, 'loyalty_program_id');
    }
}
