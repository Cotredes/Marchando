<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoyaltyProgress extends Model
{
    protected $table = 'loyalty_progress';

    protected $fillable = ['loyalty_program_id', 'customer_id', 'stamps', 'rewards_available'];

    protected function casts(): array
    {
        return ['stamps' => 'integer', 'rewards_available' => 'integer'];
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(LoyaltyProgram::class, 'loyalty_program_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
