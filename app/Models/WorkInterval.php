<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkInterval extends Model
{
    protected $fillable = ['restaurant_id', 'employee_id', 'started_at', 'ended_at', 'source', 'created_by_user_id'];

    protected function casts(): array
    {
        return ['started_at' => 'immutable_datetime', 'ended_at' => 'immutable_datetime'];
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function corrections(): HasMany
    {
        return $this->hasMany(WorkIntervalCorrection::class);
    }

    public function seconds(?\DateTimeInterface $at = null): int
    {
        return $this->started_at->diffInSeconds($this->ended_at ?: $at ?: now());
    }
}
