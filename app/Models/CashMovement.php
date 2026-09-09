<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashMovement extends Model
{
    protected $fillable = ['restaurant_id', 'cash_session_id', 'payment_id', 'user_id', 'employee_id', 'type', 'amount_minor', 'reason', 'request_key'];

    protected function casts(): array
    {
        return ['amount_minor' => 'integer'];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(CashSession::class, 'cash_session_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
