<?php

namespace App\Models;

use Database\Factories\ZoneFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Zone extends Model
{
    /** @use HasFactory<ZoneFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = ['restaurant_id', 'name', 'description', 'position', 'is_active'];

    protected function casts(): array
    {
        return ['position' => 'integer', 'is_active' => 'boolean'];
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function diningTables(): HasMany
    {
        return $this->hasMany(DiningTable::class)->orderBy('position')->orderBy('id');
    }
}
