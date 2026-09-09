<?php

namespace App\Models;

use Database\Factories\ProductFormatFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductFormat extends Model
{
    /** @use HasFactory<ProductFormatFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = ['restaurant_id', 'product_id', 'name', 'price_minor', 'cost_minor', 'position', 'is_default', 'is_active'];

    protected function casts(): array
    {
        return ['price_minor' => 'integer', 'cost_minor' => 'integer', 'position' => 'integer', 'is_default' => 'boolean', 'is_active' => 'boolean'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
