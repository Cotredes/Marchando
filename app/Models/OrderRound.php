<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class OrderRound extends Model
{
    protected $fillable = ['restaurant_id', 'order_id', 'created_by_user_id', 'created_by_employee_id', 'submitted_by_user_id', 'submitted_by_employee_id', 'sequence', 'version', 'draft_slot', 'status', 'submission_key', 'submitted_at'];

    protected function casts(): array
    {
        return ['version' => 'integer', 'sequence' => 'integer', 'draft_slot' => 'integer', 'submitted_at' => 'immutable_datetime'];
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function createdByEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'created_by_employee_id');
    }

    public function submittedByEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'submitted_by_employee_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(OrderLine::class)->orderBy('position')->orderBy('id');
    }

    public function kitchenDispatch(): HasOne
    {
        return $this->hasOne(KitchenDispatch::class, 'order_round_id');
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft' && $this->draft_slot === 1;
    }
}
