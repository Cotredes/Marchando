<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashDrawerEvent extends Model
{
    protected $fillable = ['restaurant_id', 'cash_session_id', 'payment_id', 'user_id', 'employee_id', 'trigger', 'status', 'error'];

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
