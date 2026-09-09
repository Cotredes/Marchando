<?php

namespace Tests\Feature;

use App\AttendanceService;
use App\Models\Employee;
use App\Models\OperationalRole;
use App\Models\Product;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class KitchenDisplayTest extends TestCase
{
    use RefreshDatabase;

    public function test_submitted_round_creates_kitchen_work_and_member_can_read_feed(): void
    {
        [$owner, $restaurant, $table, $waiter, $cook] = $this->setupRestaurant();
        $station = $restaurant->kitchenStations()->create(['name' => 'Cocina', 'position' => 0]);
        $product = $this->product($restaurant, 'Burger');
        $product->update(['kitchen_station_id' => $station->id]);
        $this->actingAs($owner)->post(route('restaurant.pos.tables.store', [$restaurant, $table]), ['employee_id' => $waiter->id, 'pin' => '4821']);
        $order = $restaurant->orders()->first();
        $round = $order->rounds()->first();
        $this->actingAs($owner)->post(route('restaurant.pos.rounds.lines.store', [$restaurant, $order, $round]), ['product_id' => $product->id, 'quantity' => 1]);
        $this->actingAs($owner)->post(route('restaurant.pos.rounds.submit', [$restaurant, $order, $round]));
        $item = $restaurant->kitchenItems()->firstOrFail();
        $this->assertSame($station->id, $item->kitchen_station_id);
        $this->actingAs($owner)->get(route('restaurant.kitchen.feed', $restaurant))->assertOk()->assertJsonPath('items.0.product_name', 'Burger');
    }

    public function test_kitchen_state_transitions_are_persisted_and_tenant_scoped(): void
    {
        [$owner, $restaurant, $table, $waiter, $cook] = $this->setupRestaurant();
        $product = $this->product($restaurant, 'Entrecot');
        $this->actingAs($owner)->post(route('restaurant.pos.tables.store', [$restaurant, $table]), ['employee_id' => $waiter->id, 'pin' => '4821']);
        $order = $restaurant->orders()->first();
        $round = $order->rounds()->first();
        $this->actingAs($owner)->post(route('restaurant.pos.rounds.lines.store', [$restaurant, $order, $round]), ['product_id' => $product->id, 'quantity' => 1]);
        $this->actingAs($owner)->post(route('restaurant.pos.rounds.submit', [$restaurant, $order, $round]));
        $item = $restaurant->kitchenItems()->firstOrFail();
        $this->actingAs($owner)->patch(route('restaurant.kitchen.items.state', [$restaurant, $item]), ['to' => 'preparing', 'employee_id' => $cook->id, 'pin' => '4823'])->assertOk();
        $this->assertSame('preparing', $item->fresh()->status);
        $this->actingAs($owner)->patch(route('restaurant.kitchen.items.state', [$restaurant, $item]), ['to' => 'ready', 'employee_id' => $cook->id, 'pin' => '4823'])->assertOk();
        $this->assertSame('ready', $item->fresh()->status);
    }

    private function setupRestaurant(): array
    {
        $owner = User::factory()->create();
        $restaurant = Restaurant::factory()->create();
        $owner->restaurants()->attach($restaurant, ['role' => 'owner']);
        $zone = $restaurant->zones()->create(['name' => 'Salón', 'is_active' => true]);
        $table = $zone->diningTables()->create(['restaurant_id' => $restaurant->id, 'name' => 'Mesa 4', 'is_active' => true]);
        $service = OperationalRole::create(['restaurant_id' => $restaurant->id, 'name' => 'Camarero', 'code' => 'service']);
        $kitchen = OperationalRole::create(['restaurant_id' => $restaurant->id, 'name' => 'Cocina', 'code' => 'kitchen']);
        $waiter = Employee::create(['restaurant_id' => $restaurant->id, 'first_name' => 'Ana', 'display_name' => 'ANA', 'is_active' => true, 'pin_hash' => Hash::make('4821'), 'pin_fingerprint' => AttendanceService::fingerprint($restaurant->id, '4821')]);
        $cook = Employee::create(['restaurant_id' => $restaurant->id, 'first_name' => 'Carlos', 'display_name' => 'CARLOS', 'is_active' => true, 'pin_hash' => Hash::make('4823'), 'pin_fingerprint' => AttendanceService::fingerprint($restaurant->id, '4823')]);
        $waiter->operationalRoles()->attach($service);
        $cook->operationalRoles()->attach($kitchen);

        return [$owner, $restaurant, $table, $waiter, $cook];
    }

    private function product(Restaurant $restaurant, string $name): Product
    {
        $category = $restaurant->categories()->create(['name' => 'Carta', 'is_active' => true, 'available_dine_in' => true]);

        return $category->products()->create(['restaurant_id' => $restaurant->id, 'name' => $name, 'price_minor' => 1000, 'vat_rate' => 10, 'is_active' => true, 'is_available' => true, 'available_dine_in' => true]);
    }
}
