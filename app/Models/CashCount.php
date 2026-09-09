<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashCount extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['cash_session_id', 'user_id', 'employee_id', 'declared_cash_minor', 'expected_cash_minor', 'difference_minor', 'denominations'];

    protected function casts(): array
    {
        return ['declared_cash_minor' => 'integer', 'expected_cash_minor' => 'integer', 'difference_minor' => 'integer', 'denominations' => 'array', 'created_at' => 'immutable_datetime'];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(CashSession::class, 'cash_session_id');
    }
}
