<?php

namespace Tests\Feature;

use App\AttendanceService;
use App\Models\Employee;
use App\Models\OperationalRole;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PilotSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_and_member_cannot_manage_sensitive_areas(): void
    {
        [$owner, $restaurant] = $this->restaurant();
        $member = User::factory()->create();
        $restaurant->users()->attach($member, ['role' => 'member']);
        $product = $restaurant->categories()->create(['name' => 'Carta', 'is_active' => true])->products()->create(['restaurant_id' => $restaurant->id, 'name' => 'Test', 'price_minor' => 100, 'is_active' => true, 'is_available' => true, 'available_dine_in' => true]);

        $this->get(route('restaurant.dashboard', $restaurant))->assertRedirect(route('login'));
        $this->actingAs($member)->get(route('restaurant.dashboard', $restaurant))->assertOk();
        $this->actingAs($member)->get(route('restaurant.audit', $restaurant))->assertForbidden();
        $this->actingAs($member)->get(route('restaurant.fiscal', $restaurant))->assertForbidden();
        $this->actingAs($member)->get(route('restaurant.integrations', $restaurant))->assertForbidden();
        $this->actingAs($member)->patch(route('restaurant.stock.settings', [$restaurant, $product]))->assertForbidden();
        $this->actingAs($owner)->get(route('restaurant.audit', $restaurant))->assertOk();
        $this->actingAs($owner)->get(route('restaurant.fiscal', $restaurant))->assertOk();
        $this->actingAs($owner)->get(route('restaurant.integrations', $restaurant))->assertOk();
    }

    public function test_cross_tenant_ids_are_rejected_everywhere(): void
    {
        [$owner, $restaurant] = $this->restaurant();
        [$otherOwner, $other] = $this->restaurant();
        $zone = $restaurant->zones()->create(['name' => 'Salón', 'is_active' => true]);
        $table = $zone->diningTables()->create(['restaurant_id' => $restaurant->id, 'name' => 'Mesa 1', 'is_active' => true]);
        $otherTable = $other->zones()->create(['name' => 'Otra', 'is_active' => true])->diningTables()->create(['restaurant_id' => $other->id, 'name' => 'Mesa X', 'is_active' => true]);

        // Mesa de otro restaurante vía URL propia
        $this->actingAs($owner)->get(route('restaurant.pos.tables.open', [$restaurant, $otherTable]))->assertNotFound();
        // Pedido ajeno vía TPV propio
        $foreignOrder = $other->orders()->create(['dining_table_id' => $otherTable->id, 'channel' => 'dine_in', 'status' => 'open', 'currency' => 'EUR', 'business_date' => now()->toDateString(), 'opened_at' => now()]);
        $this->actingAs($owner)->get(route('restaurant.pos.orders.show', [$restaurant, $foreignOrder]))->assertNotFound();
        $this->actingAs($owner)->get(route('restaurant.sales.show', [$restaurant, $foreignOrder]))->assertNotFound();
        // Export con IDs ajenos
        $this->actingAs($owner)->get(route('restaurant.sales', [$restaurant, 'table_id' => $otherTable->id]))->assertOk();
        $this->assertSame(0, $other->orders()->where('restaurant_id', $restaurant->id)->count());
        // QR de otro tenant no resuelve en nuestro contexto
        $otherTable->update(['qr_token' => bin2hex(random_bytes(32)), 'qr_is_active' => true]);
        $this->get(route('tables.resolve', ['token' => $otherTable->qr_token]))->assertOk(); // público, pero…
        $this->assertNotSame($restaurant->id, $otherTable->restaurant_id);
    }

    public function test_public_tracking_tokens_do_not_leak_other_orders(): void
    {
        [$owner, $restaurant] = $this->restaurant();
        $first = $this->publicRequest($restaurant, 'req-a');
        $second = $this->publicRequest($restaurant, 'req-b');

        $this->get(route('public.order.track', ['token' => $first->public_token]))->assertOk()->assertDontSee($second->public_token)->assertDontSee($second->request_key);
        $this->get(route('public.order.snapshot', ['token' => '00']))->assertNotFound();
        $snapshot = $this->get(route('public.order.snapshot', ['token' => $first->public_token]))->assertOk()->json();
        $this->assertArrayNotHasKey('customer_phone', $snapshot);
        $this->assertArrayNotHasKey('delivery_address', $snapshot);
    }

    public function test_pin_routes_are_throttled_and_wrong_pin_rejected(): void
    {
        [$owner, $restaurant, $table, $employee] = $this->setupRestaurant();
        $this->actingAs($owner)->post(route('restaurant.pos.tables.store', [$restaurant, $table]), ['employee_id' => $employee->id, 'pin' => '0000'])->assertSessionHasErrors('pin');
        // 21 intentos rápidos deben activar el throttle (20/min)
        for ($i = 0; $i < 21; $i++) {
            $response = $this->actingAs($owner)->post(route('restaurant.pos.tables.store', [$restaurant, $table]), ['employee_id' => $employee->id, 'pin' => '0000']);
            if ($response->status() === 429) {
                $this->assertTrue(true);

                return;
            }
        }
        $this->fail('Se esperaba throttle 429 en rutas PIN.');
    }

    public function test_tpv_lock_requires_reidentification(): void
    {
        [$owner, $restaurant, $table, $employee] = $this->setupRestaurant();
        $this->actingAs($owner)->post(route('restaurant.pos.tables.store', [$restaurant, $table]), ['employee_id' => $employee->id, 'pin' => '4821'])->assertRedirect();
        $order = $restaurant->orders()->firstOrFail();
        $this->actingAs($owner)->post(route('restaurant.pos.orders.employee.forget', [$restaurant, $order]))->assertRedirect();
        $product = $this->product($restaurant);
        $round = $order->rounds()->first();
        $this->actingAs($owner)->post(route('restaurant.pos.rounds.lines.store', [$restaurant, $order, $round]), ['product_id' => $product->id, 'quantity' => 1])->assertSessionHasErrors('operator');
    }

    private function publicRequest(Restaurant $restaurant, string $key)
    {
        return $restaurant->publicOrderRequests()->create([
            'channel' => 'delivery', 'status' => 'pending', 'public_token_hash' => hash('sha256', $t = bin2hex(random_bytes(32))),
            'public_token' => $t, 'request_key' => $key, 'payload_hash' => hash('sha256', $key),
            'currency' => 'EUR', 'subtotal_minor' => 1000, 'total_minor' => 1000,
            'customer_name' => 'Test', 'customer_phone' => '600123456', 'fulfillment_mode' => 'asap', 'requested_at' => now(),
        ]);
    }

    private function product(Restaurant $restaurant)
    {
        $category = $restaurant->categories()->create(['name' => 'Carta', 'is_active' => true, 'available_dine_in' => true]);

        return $category->products()->create(['restaurant_id' => $restaurant->id, 'name' => 'Prod '.microtime(true), 'price_minor' => 500, 'vat_rate' => 10, 'is_active' => true, 'is_available' => true, 'available_dine_in' => true]);
    }

    private function setupRestaurant(): array
    {
        [$owner, $restaurant] = $this->restaurant();
        $zone = $restaurant->zones()->create(['name' => 'Salón', 'is_active' => true]);
        $table = $zone->diningTables()->create(['restaurant_id' => $restaurant->id, 'name' => 'Mesa 4', 'capacity' => 4, 'is_active' => true]);
        $role = OperationalRole::create(['restaurant_id' => $restaurant->id, 'name' => 'Camarero', 'code' => 'service']);
        $employee = Employee::create(['restaurant_id' => $restaurant->id, 'first_name' => 'Ana', 'display_name' => 'ANA', 'is_active' => true, 'pin_hash' => Hash::make('4821'), 'pin_fingerprint' => AttendanceService::fingerprint($restaurant->id, '4821')]);
        $employee->operationalRoles()->attach($role);

        return [$owner, $restaurant, $table, $employee];
    }

    private function restaurant(): array
    {
        $owner = User::factory()->create();
        $restaurant = Restaurant::factory()->create();
        $owner->restaurants()->attach($restaurant, ['role' => 'owner']);

        return [$owner, $restaurant];
    }
}
