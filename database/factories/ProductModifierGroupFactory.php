<?php

namespace Database\Factories;

use App\Models\ModifierGroup;
use App\Models\Product;
use App\Models\ProductModifierGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductModifierGroup>
 */
class ProductModifierGroupFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'restaurant_id' => fn (array $attributes): int => Product::query()->findOrFail($attributes['product_id'])->restaurant_id,
            'product_id' => Product::factory(),
            'modifier_group_id' => ModifierGroup::factory(),
            'position' => 0,
        ];
    }
}
