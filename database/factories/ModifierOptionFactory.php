<?php

namespace Database\Factories;

use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ModifierOption>
 */
class ModifierOptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'restaurant_id' => fn (array $attributes): int => ModifierGroup::query()->findOrFail($attributes['modifier_group_id'])->restaurant_id,
            'modifier_group_id' => ModifierGroup::factory(),
            'name' => fake()->word(),
            'price_delta_minor' => 0,
            'max_quantity' => 1,
            'instruction' => 'normal',
            'is_active' => true,
            'position' => 0,
        ];
    }
}
