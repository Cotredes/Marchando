<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Restaurant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'restaurant_id' => Restaurant::factory(),
            'name' => fake()->unique()->words(2, true),
            'description' => fake()->optional()->sentence(),
            'position' => fake()->numberBetween(0, 50),
            'is_active' => true,
            'available_dine_in' => true,
            'available_takeaway' => false,
            'available_delivery' => false,
        ];
    }
}
