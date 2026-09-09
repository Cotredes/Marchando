<?php

namespace Database\Factories;

use App\Models\ModifierGroup;
use App\Models\Restaurant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ModifierGroup>
 */
class ModifierGroupFactory extends Factory
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
            'min_selections' => 0,
            'max_selections' => 1,
            'allow_quantities' => false,
            'is_active' => true,
        ];
    }
}
