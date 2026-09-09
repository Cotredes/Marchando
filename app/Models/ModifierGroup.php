<?php

namespace App\Models;

use Database\Factories\ModifierGroupFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ModifierGroup extends Model
{
    /** @use HasFactory<ModifierGroupFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = ['restaurant_id', 'name', 'description', 'min_selections', 'max_selections', 'allow_quantities', 'is_active'];

    protected function casts(): array
    {
        return ['min_selections' => 'integer', 'max_selections' => 'integer', 'allow_quantities' => 'boolean', 'is_active' => 'boolean'];
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(ModifierOption::class)->orderBy('position')->orderBy('id');
    }

    public function modifierOptions(): HasMany
    {
        return $this->options();
    }

    public function productAssignments(): HasMany
    {
        return $this->hasMany(ProductModifierGroup::class)->orderBy('position')->orderBy('id');
    }

    public function usageCount(): int
    {
        return $this->productAssignments()->count();
    }
}
