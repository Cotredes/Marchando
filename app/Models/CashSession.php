<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashSession extends Model
{
    protected $fillable = ['restaurant_id', 'cash_register_id', 'opened_by_user_id', 'opened_by_employee_id', 'closed_by_user_id', 'closed_by_employee_id', 'status', 'open_slot', 'opening_float_minor', 'expected_cash_minor', 'declared_cash_minor', 'difference_minor', 'opened_at', 'closed_at'];

    protected function casts(): array
    {
        return ['opening_float_minor' => 'integer', 'expected_cash_minor' => 'integer', 'declared_cash_minor' => 'integer', 'difference_minor' => 'integer', 'opened_at' => 'immutable_datetime', 'closed_at' => 'immutable_datetime'];
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function register(): BelongsTo
    {
        return $this->belongsTo(CashRegister::class, 'cash_register_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(CashMovement::class);
    }

    public function counts(): HasMany
    {
        return $this->hasMany(CashCount::class);
    }
}
