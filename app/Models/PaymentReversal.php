<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentReversal extends Model
{
    protected $fillable = ['payment_id', 'restaurant_id', 'user_id', 'employee_id', 'amount_minor', 'reason', 'request_key'];

    protected function casts(): array
    {
        return ['amount_minor' => 'integer'];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
