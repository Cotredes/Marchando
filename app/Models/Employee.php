<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['restaurant_id', 'user_id', 'first_name', 'last_name', 'display_name', 'phone', 'email', 'pin_hash', 'pin_fingerprint', 'is_active'];

    protected $hidden = ['pin_hash', 'pin_fingerprint'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function operationalRoles(): BelongsToMany
    {
        return $this->belongsToMany(OperationalRole::class, 'employee_operational_role');
    }

    public function workIntervals(): HasMany
    {
        return $this->hasMany(WorkInterval::class);
    }

    public function ordersOpened(): HasMany
    {
        return $this->hasMany(Order::class, 'opened_by_employee_id');
    }

    public function ordersCurrent(): HasMany
    {
        return $this->hasMany(Order::class, 'current_employee_id');
    }

    public function orderLines(): HasMany
    {
        return $this->hasMany(OrderLine::class);
    }

    public function openInterval(): HasMany
    {
        return $this->workIntervals()->whereNull('ended_at');
    }

    public function hasPin(): bool
    {
        return filled($this->pin_hash);
    }

    public function roleNames(): string
    {
        return $this->operationalRoles->pluck('name')->join(' + ');
    }
}
