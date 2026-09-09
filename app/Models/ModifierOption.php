<?php

namespace App\Models;

use Database\Factories\ModifierOptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ModifierOption extends Model
{
    /** @use HasFactory<ModifierOptionFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = ['restaurant_id', 'modifier_group_id', 'name', 'price_delta_minor', 'max_quantity', 'instruction', 'is_active', 'position'];

    protected function casts(): array
    {
        return ['price_delta_minor' => 'integer', 'max_quantity' => 'integer', 'position' => 'integer', 'is_active' => 'boolean'];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(ModifierGroup::class, 'modifier_group_id');
    }
}
