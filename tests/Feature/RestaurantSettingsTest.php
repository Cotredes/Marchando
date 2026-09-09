<?php

namespace Tests\Feature;

use App\Models\OpeningHour;
use App\Models\Restaurant;
use App\Models\User;
use App\RestaurantAvailability;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RestaurantSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_member_can_view_settings_but_only_an_owner_can_update_them(): void
    {
        $member = User::factory()->create();
        $restaurant = Restaurant::factory()->create();
        $restaurant->users()->attach($member, ['role' => 'member']);

        $this->actingAs($member)->get(route('restaurant.settings', $restaurant))->assertOk();
        $this->actingAs($member)->patch(route('restaurant.settings.details.update', $restaurant), $this->details())
            ->assertForbidden();
    }

    public function test_an_owner_can_persist_restaurant_details_without_affecting_another_restaurant(): void
    {
        $owner = User::factory()->create();
        $restaurant = Restaurant::factory()->create(['name' => 'Original']);
        $other = Restaurant::factory()->create(['name' => 'No tocar']);
        $restaurant->users()->attach($owner, ['role' => 'owner']);

        $this->actingAs($owner)
            ->patch(route('restaurant.settings.details.update', $restaurant), $this->details(['name' => 'Actualizado']))
            ->assertRedirect(route('restaurant.settings', $restaurant).'#general');

        $this->assertDatabaseHas('restaurants', ['id' => $restaurant->id, 'name' => 'Actualizado']);
        $this->assertDatabaseHas('restaurants', ['id' => $other->id, 'name' => 'No tocar']);
    }

    public function test_a_user_cannot_update_another_restaurants_settings_by_changing_the_url(): void
    {
        $owner = User::factory()->create();
        $restaurant = Restaurant::factory()->create();
        $other = Restaurant::factory()->create(['name' => 'Privado']);
        $restaurant->users()->attach($owner, ['role' => 'owner']);

        $this->actingAs($owner)
            ->patch(route('restaurant.settings.details.update', $other), $this->details(['name' => 'Intrusión']))
            ->assertForbidden();

        $this->assertDatabaseHas('restaurants', ['id' => $other->id, 'name' => 'Privado']);
    }

    public function test_owner_can_save_multiple_daily_periods_and_overnight_hours(): void
    {
        [$owner, $restaurant] = $this->ownedRestaurant();

        $payload = ['hours' => [
            'general' => [
                1 => ['enabled' => '1', 'intervals' => [
                    ['opens_at' => '13:00', 'closes_at' => '16:00'],
                    ['opens_at' => '20:00', 'closes_at' => '02:00'],
                ]],
            ],
        ]];

        $this->actingAs($owner)
            ->put(route('restaurant.settings.hours.update', $restaurant), $payload)
            ->assertRedirect(route('restaurant.settings', $restaurant).'#hours');

        $this->assertDatabaseHas('opening_hours', [
            'restaurant_id' => $restaurant->id,
            'iso_weekday' => 1,
            'start_minute' => 1200,
            'end_minute' => 1560,
        ]);
        $this->assertSame(2, OpeningHour::query()->where('restaurant_id', $restaurant->id)->count());
    }

    public function test_overlapping_hours_are_rejected_without_replacing_existing_hours(): void
    {
        [$owner, $restaurant] = $this->ownedRestaurant();
        $restaurant->openingHours()->create([
            'context' => 'general',
            'iso_weekday' => 1,
            'start_minute' => 540,
            'end_minute' => 720,
        ]);

        $this->actingAs($owner)
            ->put(route('restaurant.settings.hours.update', $restaurant), ['hours' => [
                'general' => [1 => ['enabled' => '1', 'intervals' => [
                    ['opens_at' => '13:00', 'closes_at' => '16:00'],
                    ['opens_at' => '15:00', 'closes_at' => '17:00'],
                ]]],
            ]])
            ->assertSessionHasErrors();

        $this->assertDatabaseHas('opening_hours', ['restaurant_id' => $restaurant->id, 'start_minute' => 540]);
    }

    public function test_availability_honours_overnight_periods_and_disabled_channels(): void
    {
        [$owner, $restaurant] = $this->ownedRestaurant();
        $restaurant->update([
            'timezone' => 'Europe/Madrid',
            'takeaway_enabled' => true,
            'takeaway_use_general_schedule' => true,
            'delivery_enabled' => false,
        ]);
        $restaurant->openingHours()->create([
            'context' => 'general',
            'iso_weekday' => 5,
            'start_minute' => 1320,
            'end_minute' => 1560,
        ]);
        $availability = new RestaurantAvailability;

        $this->assertTrue($availability->isOpenAt($restaurant, CarbonImmutable::parse('2026-09-12 01:30:00', 'Europe/Madrid')));
        $this->assertFalse($availability->isOpenAt($restaurant, CarbonImmutable::parse('2026-09-12 02:00:00', 'Europe/Madrid')));
        $this->assertTrue($availability->isTakeawayOpenAt($restaurant, CarbonImmutable::parse('2026-09-12 01:30:00', 'Europe/Madrid')));
        $this->assertFalse($availability->isDeliveryOpenAt($restaurant, CarbonImmutable::parse('2026-09-12 01:30:00', 'Europe/Madrid')));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function details(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Restaurante Nuevo',
            'country' => 'España',
            'timezone' => 'Europe/Madrid',
        ], $overrides);
    }

    /**
     * @return array{0: User, 1: Restaurant}
     */
    private function ownedRestaurant(): array
    {
        $owner = User::factory()->create();
        $restaurant = Restaurant::factory()->create();
        $restaurant->users()->attach($owner, ['role' => 'owner']);

        return [$owner, $restaurant];
    }
}
