<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    public function configure(): static
    {
        return $this->afterMaking(function (Product $product): void {
            $product->restaurant_id = Category::query()->findOrFail($product->category_id)->restaurant_id;
        });
    }

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'restaurant_id' => null,
            'category_id' => Category::factory(),
            'name' => fake()->unique()->words(2, true),
            'short_name' => null,
            'description' => fake()->optional()->sentence(),
            'price_minor' => fake()->numberBetween(500, 2500),
            'cost_minor' => null,
            'vat_rate' => null,
            'is_active' => true,
            'is_available' => true,
            'available_dine_in' => true,
            'available_takeaway' => false,
            'available_delivery' => false,
            'position' => 0,
        ];
    }
}
