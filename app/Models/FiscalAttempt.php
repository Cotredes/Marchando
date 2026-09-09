<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FiscalAttempt extends Model
{
    protected $fillable = ['fiscal_record_id', 'status', 'response_code', 'response_body'];

    public function record(): BelongsTo
    {
        return $this->belongsTo(FiscalRecord::class, 'fiscal_record_id');
    }
}
