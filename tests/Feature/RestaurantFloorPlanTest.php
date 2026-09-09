<?php

namespace Tests\Feature;

use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RestaurantFloorPlanTest extends TestCase
{
    use RefreshDatabase;

    public function test_members_can_view_floor_plan_but_only_owners_can_manage_it(): void
    {
        [$owner, $restaurant] = $this->restaurant();
        $member = User::factory()->create();
        $restaurant->users()->attach($member, ['role' => 'member']);

        $this->actingAs($member)->get(route('restaurant.restaurant', $restaurant))->assertOk();
        $this->actingAs($member)->post(route('restaurant.restaurant.zones.store', $restaurant), ['name' => 'No'])->assertForbidden();
        $this->actingAs($owner)->post(route('restaurant.restaurant.zones.store', $restaurant), ['name' => 'Salón', 'is_active' => 1])->assertRedirect();
        $this->assertDatabaseHas('zones', ['restaurant_id' => $restaurant->id, 'name' => 'Salón']);
    }

    public function test_owner_can_create_batch_tables_with_unique_tokens(): void
    {
        [$owner, $restaurant] = $this->restaurant();
        $zone = $restaurant->zones()->create(['name' => 'Terraza', 'is_active' => true]);

        $this->actingAs($owner)->post(route('restaurant.restaurant.tables.batch-store', [$restaurant, $zone]), [
            'prefix' => 'Mesa', 'start_number' => 1, 'count' => 5, 'capacity' => 4, 'is_active' => 1, 'qr_is_active' => 1,
        ])->assertRedirect();

        $tables = $zone->diningTables()->get();
        $this->assertCount(5, $tables);
        $this->assertCount(5, $tables->pluck('qr_token')->unique());
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $tables->first()->qr_token);
    }

    public function test_batch_creation_rolls_back_when_a_name_already_exists(): void
    {
        [$owner, $restaurant] = $this->restaurant();
        $zone = $restaurant->zones()->create(['name' => 'Salón']);
        $zone->diningTables()->create(['restaurant_id' => $restaurant->id, 'name' => 'Mesa 2', 'qr_token' => bin2hex(random_bytes(32))]);

        $this->actingAs($owner)->post(route('restaurant.restaurant.tables.batch-store', [$restaurant, $zone]), ['prefix' => 'Mesa', 'start_number' => 1, 'count' => 3, 'capacity' => 2])->assertSessionHasErrors('prefix');
        $this->assertSame(1, $zone->diningTables()->count());
    }

    public function test_zone_with_tables_cannot_be_archived(): void
    {
        [$owner, $restaurant] = $this->restaurant();
        $zone = $restaurant->zones()->create(['name' => 'Barra']);
        $zone->diningTables()->create(['restaurant_id' => $restaurant->id, 'name' => 'Barra 1', 'qr_token' => bin2hex(random_bytes(32))]);

        $this->actingAs($owner)->delete(route('restaurant.restaurant.zones.destroy', [$restaurant, $zone]))->assertSessionHasErrors('zone');
    }

    public function test_moving_and_renaming_a_table_preserves_identity_and_token(): void
    {
        [$owner, $restaurant] = $this->restaurant();
        $source = $restaurant->zones()->create(['name' => 'Salón']);
        $target = $restaurant->zones()->create(['name' => 'Terraza']);
        $table = $source->diningTables()->create(['restaurant_id' => $restaurant->id, 'name' => 'Mesa 8', 'qr_token' => bin2hex(random_bytes(32)), 'is_active' => true]);
        $token = $table->qr_token;

        $this->actingAs($owner)->patch(route('restaurant.restaurant.tables.update', [$restaurant, $source, $table]), ['name' => 'Terraza VIP', 'capacity' => 6, 'is_active' => 1, 'qr_is_active' => 0])->assertRedirect();
        $this->actingAs($owner)->patch(route('restaurant.restaurant.tables.transfer', [$restaurant, $source, $table]), ['target_zone_id' => $target->id])->assertRedirect();

        $table->refresh();
        $this->assertSame('Terraza VIP', $table->name);
        $this->assertSame($target->id, $table->zone_id);
        $this->assertSame($token, $table->qr_token);
    }

    public function test_qr_can_be_activated_resolved_revoked_and_regenerated(): void
    {
        [$owner, $restaurant] = $this->restaurant();
        $zone = $restaurant->zones()->create(['name' => 'Terraza', 'is_active' => true]);
        $table = $zone->diningTables()->create(['restaurant_id' => $restaurant->id, 'name' => 'Terraza 1', 'qr_token' => bin2hex(random_bytes(32)), 'is_active' => true]);
        $oldToken = $table->qr_token;

        $this->get(route('tables.resolve', ['token' => $oldToken]))->assertNotFound();
        $this->actingAs($owner)->patch(route('restaurant.restaurant.tables.qr.activate', [$restaurant, $zone, $table]))->assertRedirect();
        $this->get(route('tables.resolve', ['token' => $oldToken]))->assertOk()->assertSee('Terraza 1');
        $this->actingAs($owner)->get(route('restaurant.restaurant.tables.qr.svg', [$restaurant, $zone, $table]))->assertOk()->assertHeader('Content-Type', 'image/svg+xml; charset=UTF-8');
        $this->actingAs($owner)->post(route('restaurant.restaurant.tables.qr.regenerate', [$restaurant, $zone, $table]))->assertRedirect();
        $table->refresh();
        $this->assertNotSame($oldToken, $table->qr_token);
        $this->get(route('tables.resolve', ['token' => $oldToken]))->assertNotFound();
        $this->get(route('tables.resolve', ['token' => $table->qr_token]))->assertOk();
        $this->actingAs($owner)->patch(route('restaurant.restaurant.tables.qr.revoke', [$restaurant, $zone, $table]))->assertRedirect();
        $this->get(route('tables.resolve', ['token' => $table->qr_token]))->assertNotFound();
    }

    public function test_cross_tenant_zone_cannot_receive_a_table(): void
    {
        [$owner, $restaurant] = $this->restaurant();
        $other = Restaurant::factory()->create();
        $foreignZone = $other->zones()->create(['name' => 'Ajena']);

        $this->actingAs($owner)->post(route('restaurant.restaurant.tables.store', [$restaurant, $foreignZone]), ['name' => 'Intrusa', 'capacity' => 2])->assertNotFound();
    }

    public function test_inactive_zone_hides_tables_from_floor_plan_and_public_qr(): void
    {
        [$owner, $restaurant] = $this->restaurant();
        $zone = $restaurant->zones()->create(['name' => 'Invierno', 'is_active' => false]);
        $table = $zone->diningTables()->create(['restaurant_id' => $restaurant->id, 'name' => 'Terraza 1', 'qr_token' => bin2hex(random_bytes(32)), 'is_active' => true, 'qr_is_active' => true]);

        $this->actingAs($owner)->get(route('restaurant.restaurant', $restaurant))->assertDontSee('Terraza 1');
        $this->get(route('tables.resolve', ['token' => $table->qr_token]))->assertNotFound();
    }

    public function test_qr_sheet_is_scoped_to_one_zone(): void
    {
        [$owner, $restaurant] = $this->restaurant();
        $zone = $restaurant->zones()->create(['name' => 'Salón']);
        $other = $restaurant->zones()->create(['name' => 'Terraza']);
        $zone->diningTables()->create(['restaurant_id' => $restaurant->id, 'name' => 'Mesa Salón', 'qr_token' => bin2hex(random_bytes(32)), 'qr_is_active' => true]);
        $other->diningTables()->create(['restaurant_id' => $restaurant->id, 'name' => 'Mesa Terraza', 'qr_token' => bin2hex(random_bytes(32)), 'qr_is_active' => true]);

        $this->actingAs($owner)->get(route('restaurant.restaurant.zones.qr-sheet', [$restaurant, $zone]))->assertOk()->assertSee('Mesa Salón')->assertDontSee('Mesa Terraza');
    }

    /** @return array{0: User, 1: Restaurant} */
    private function restaurant(): array
    {
        $owner = User::factory()->create();
        $restaurant = Restaurant::factory()->create();
        $restaurant->users()->attach($owner, ['role' => 'owner']);

        return [$owner, $restaurant];
    }
}
