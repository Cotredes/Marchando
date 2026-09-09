<?php

namespace Database\Factories;

use App\Models\Restaurant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Restaurant>
 */
class RestaurantFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'slug' => fake()->unique()->slug(),
            'default_vat' => 10,
            'currency' => 'EUR',
            'timezone' => 'Europe/Madrid',
            'dine_in_enabled' => true,
            'takeaway_enabled' => false,
            'delivery_enabled' => false,
        ];
    }
}
