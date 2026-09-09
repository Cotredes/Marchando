<?php

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'restaurant_id', 'category_id', 'name', 'short_name', 'description', 'price_minor', 'cost_minor', 'vat_rate',
        'is_active', 'is_available', 'track_stock', 'stock_quantity', 'stock_minimum', 'allows_manual_price', 'kitchen_station_id', 'available_dine_in', 'available_takeaway',
        'available_delivery', 'position', 'image_path',
    ];

    protected function casts(): array
    {
        return [
            'price_minor' => 'integer',
            'cost_minor' => 'integer',
            'vat_rate' => 'decimal:2',
            'is_active' => 'boolean',
            'is_available' => 'boolean',
            'track_stock' => 'boolean',
            'stock_quantity' => 'integer',
            'stock_minimum' => 'integer',
            'allows_manual_price' => 'boolean',
            'available_dine_in' => 'boolean',
            'available_takeaway' => 'boolean',
            'available_delivery' => 'boolean',
            'position' => 'integer',
        ];
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function kitchenStation(): BelongsTo
    {
        return $this->belongsTo(KitchenStation::class);
    }

    public function allergens(): BelongsToMany
    {
        return $this->belongsToMany(Allergen::class);
    }

    public function formats(): HasMany
    {
        return $this->hasMany(ProductFormat::class)->orderBy('position')->orderBy('id');
    }

    public function modifierGroupAssignments(): HasMany
    {
        return $this->hasMany(ProductModifierGroup::class)->orderBy('position')->orderBy('id');
    }

    public function modifierGroups(): BelongsToMany
    {
        return $this->belongsToMany(ModifierGroup::class, 'product_modifier_groups')
            ->withPivot('position')
            ->withTimestamps();
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function effectiveVat(): ?string
    {
        return $this->vat_rate ?? $this->restaurant?->default_vat;
    }

    public function isOutOfStock(): bool
    {
        return $this->track_stock && $this->stock_quantity <= 0;
    }

    public function isLowStock(): bool
    {
        return $this->track_stock && $this->stock_minimum !== null && $this->stock_quantity <= $this->stock_minimum;
    }

    public function stockStatus(): string
    {
        if (! $this->track_stock) {
            return 'untracked';
        }

        if ($this->isOutOfStock()) {
            return 'out';
        }

        return $this->isLowStock() ? 'low' : 'ok';
    }

    public function isEffectivelyAvailable(string $channel): bool
    {
        $productChannel = match ($channel) {
            'dine_in' => $this->available_dine_in,
            'takeaway' => $this->available_takeaway,
            'delivery' => $this->available_delivery,
            default => false,
        };
        $categoryChannel = match ($channel) {
            'dine_in' => $this->category?->available_dine_in,
            'takeaway' => $this->category?->available_takeaway,
            'delivery' => $this->category?->available_delivery,
            default => false,
        };
        $restaurantChannel = match ($channel) {
            'dine_in' => $this->restaurant?->dine_in_enabled,
            'takeaway' => $this->restaurant?->takeaway_enabled,
            'delivery' => $this->restaurant?->delivery_enabled,
            default => false,
        };

        return $this->is_active && $this->is_available && ($this->category?->is_active ?? false)
            && $productChannel && $categoryChannel && $restaurantChannel;
    }
}
