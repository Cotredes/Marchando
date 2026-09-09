<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OnlineRefund extends Model
{
    protected $fillable = ['restaurant_id', 'online_payment_intent_id', 'user_id', 'employee_id', 'amount_minor', 'reason', 'status', 'provider_refund_id', 'failure_reason', 'request_key'];

    protected function casts(): array
    {
        return ['amount_minor' => 'integer'];
    }

    public function intent(): BelongsTo
    {
        return $this->belongsTo(OnlinePaymentIntent::class, 'online_payment_intent_id');
    }
}
