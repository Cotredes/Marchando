<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkIntervalCorrection extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['restaurant_id', 'work_interval_id', 'user_id', 'actor_name', 'previous_started_at', 'previous_ended_at', 'corrected_started_at', 'corrected_ended_at', 'reason'];

    protected function casts(): array
    {
        return ['previous_started_at' => 'immutable_datetime', 'previous_ended_at' => 'immutable_datetime', 'corrected_started_at' => 'immutable_datetime', 'corrected_ended_at' => 'immutable_datetime', 'created_at' => 'immutable_datetime'];
    }

    public function interval(): BelongsTo
    {
        return $this->belongsTo(WorkInterval::class, 'work_interval_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
