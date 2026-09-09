<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentTender extends Model
{
    protected $fillable = ['payment_id', 'payment_method_id', 'amount_minor', 'tendered_minor', 'change_minor'];

    protected function casts(): array
    {
        return ['amount_minor' => 'integer', 'tendered_minor' => 'integer', 'change_minor' => 'integer'];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function method(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method_id');
    }
}
