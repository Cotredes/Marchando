<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Printer extends Model
{
    public const USES = ['kitchen', 'bar', 'ticket', 'invoice', 'cash'];

    protected $fillable = ['restaurant_id', 'name', 'uses', 'paper_width', 'connection', 'endpoint', 'is_active', 'open_drawer', 'last_seen_at', 'status_note'];

    protected function casts(): array
    {
        return ['uses' => 'array', 'is_active' => 'boolean', 'open_drawer' => 'boolean', 'paper_width' => 'integer', 'last_seen_at' => 'immutable_datetime'];
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function stations(): BelongsToMany
    {
        return $this->belongsToMany(KitchenStation::class, 'kitchen_station_printer', 'printer_id', 'kitchen_station_id')->withPivot('position')->orderByPivot('position');
    }

    public function jobs(): HasMany
    {
        return $this->hasMany(PrintJob::class);
    }

    public function serves(string $use): bool
    {
        return $this->is_active && in_array($use, $this->uses ?? [], true);
    }
}
