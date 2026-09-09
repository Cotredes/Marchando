<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class KitchenStation extends Model
{
    use SoftDeletes;

    protected $fillable = ['restaurant_id', 'name', 'position', 'is_active', 'warning_after_seconds', 'late_after_seconds'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'position' => 'integer', 'warning_after_seconds' => 'integer', 'late_after_seconds' => 'integer'];
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(KitchenItem::class);
    }

    public function printers(): BelongsToMany
    {
        return $this->belongsToMany(Printer::class, 'kitchen_station_printer', 'kitchen_station_id', 'printer_id')->withPivot('position')->orderByPivot('position');
    }
}
