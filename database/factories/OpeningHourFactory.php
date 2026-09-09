<?php

namespace Database\Factories;

use App\Models\OpeningHour;
use App\Models\Restaurant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OpeningHour>
 */
class OpeningHourFactory extends Factory
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
            'context' => 'general',
            'iso_weekday' => fake()->numberBetween(1, 7),
            'start_minute' => 13 * 60,
            'end_minute' => 16 * 60,
        ];
    }
}
