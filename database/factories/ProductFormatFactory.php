<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductFormat;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductFormat>
 */
class ProductFormatFactory extends Factory
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
            'name' => fake()->word(),
            'price_minor' => fake()->numberBetween(500, 2500),
            'cost_minor' => null,
            'position' => 0,
            'is_default' => true,
            'is_active' => true,
        ];
    }
}
