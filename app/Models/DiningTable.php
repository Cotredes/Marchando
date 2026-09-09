<?php

namespace App\Models;

use Database\Factories\DiningTableFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class DiningTable extends Model
{
    /** @use HasFactory<DiningTableFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = ['restaurant_id', 'zone_id', 'name', 'capacity', 'position', 'is_active', 'qr_is_active'];

    protected static function booted(): void
    {
        static::creating(function (DiningTable $table): void {
            if (! $table->qr_token) {
                $table->qr_token = bin2hex(random_bytes(32));
            }
        });
    }

    protected function casts(): array
    {
        return ['capacity' => 'integer', 'position' => 'integer', 'is_active' => 'boolean', 'qr_is_active' => 'boolean', 'qr_activated_at' => 'immutable_datetime', 'qr_revoked_at' => 'immutable_datetime'];
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    public function activeAssignment(): HasOne
    {
        return $this->hasOne(ActiveTableOrder::class);
    }

    public function activeOrder(): HasOne
    {
        return $this->hasOne(Order::class)->where('status', 'open');
    }

    public function hasActiveQr(): bool
    {
        return $this->qr_is_active && $this->is_active && $this->zone?->is_active === true;
    }
}
