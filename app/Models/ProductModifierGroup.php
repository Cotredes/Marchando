<?php

namespace App\Models;

use Database\Factories\ProductModifierGroupFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductModifierGroup extends Model
{
    /** @use HasFactory<ProductModifierGroupFactory> */
    use HasFactory;

    protected $fillable = ['restaurant_id', 'product_id', 'modifier_group_id', 'position'];

    protected function casts(): array
    {
        return ['position' => 'integer'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(ModifierGroup::class, 'modifier_group_id');
    }
}
