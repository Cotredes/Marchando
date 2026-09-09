<?php

namespace App\Models;

use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'restaurant_id', 'name', 'description', 'position', 'is_active',
        'available_dine_in', 'available_takeaway', 'available_delivery', 'image_path',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'is_active' => 'boolean',
            'available_dine_in' => 'boolean',
            'available_takeaway' => 'boolean',
            'available_delivery' => 'boolean',
        ];
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
