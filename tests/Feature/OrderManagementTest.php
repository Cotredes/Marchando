<?php

namespace Tests\Feature;

use App\AttendanceService;
use App\Models\DiningTable;
use App\Models\Employee;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use App\Models\OperationalRole;
use App\Models\Product;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OrderManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_open_table_add_and_confirm_a_persisted_round(): void
    {
        [$owner, $restaurant, $table, $employee] = $this->setupRestaurant();
        $product = $this->product($restaurant, 'Coca-Cola', 250);
        $this->actingAs($owner)->get(route('restaurant.pos', $restaurant))->assertOk();
        $this->actingAs($owner)->post(route('restaurant.pos.tables.store', [$restaurant, $table]), ['employee_id' => $employee->id, 'pin' => '4821', 'guest_count' => 4])->assertRedirect();
        $order = $restaurant->orders()->firstOrFail();
        $round = $order->rounds()->firstOrFail();
        $this->actingAs($owner)->post(route('restaurant.pos.rounds.lines.store', [$restaurant, $order, $round]), ['product_id' => $product->id, 'quantity' => 3])->assertRedirect();
        $this->assertDatabaseHas('order_lines', ['order_id' => $order->id, 'quantity' => 3, 'line_total_minor' => 750]);
        $this->actingAs($owner)->post(route('restaurant.pos.rounds.submit', [$restaurant, $order, $round]))->assertRedirect();
        $this->assertDatabaseHas('order_rounds', ['id' => $round->id, 'status' => 'submitted']);
        $this->assertDatabaseHas('active_table_orders', ['dining_table_id' => $table->id, 'order_id' => $order->id]);
        $this->assertSame(750, $order->fresh()->total_minor);
        $this->actingAs($owner)->get(route('restaurant.pos.orders.show', [$restaurant, $order]))->assertOk();
    }

    public function test_one_active_account_per_table_and_empty_account_can_be_cancelled(): void
    {
        [$owner, $restaurant, $table, $employee] = $this->setupRestaurant();
        $this->actingAs($owner)->post(route('restaurant.pos.tables.store', [$restaurant, $table]), ['employee_id' => $employee->id, 'pin' => '4821'])->assertRedirect();
        $this->actingAs($owner)->post(route('restaurant.pos.tables.store', [$restaurant, $table]), ['employee_id' => $employee->id, 'pin' => '4821'])->assertRedirect();
        $this->assertCount(1, $restaurant->orders()->get());
        $order = $restaurant->orders()->first();
        $this->actingAs($owner)->post(route('restaurant.pos.orders.cancel', [$restaurant, $order]))->assertRedirect();
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertDatabaseCount('active_table_orders', 0);
    }

    public function test_format_and_modifier_snapshot_are_server_calculated(): void
    {
        [$owner, $restaurant, $table, $employee] = $this->setupRestaurant();
        $product = $this->product($restaurant, 'Entrecot', 2200);
        $format = $product->formats()->create(['restaurant_id' => $restaurant->id, 'name' => '300 g', 'price_minor' => 2200, 'is_active' => true, 'is_default' => true]);
        $group = ModifierGroup::create(['restaurant_id' => $restaurant->id, 'name' => 'Punto', 'min_selections' => 1, 'max_selections' => 1, 'allow_quantities' => false, 'is_active' => true]);
        $option = ModifierOption::create(['restaurant_id' => $restaurant->id, 'modifier_group_id' => $group->id, 'name' => 'Poco hecho', 'price_delta_minor' => 0, 'max_quantity' => 1, 'instruction' => 'normal', 'is_active' => true]);
        $product->modifierGroupAssignments()->create(['restaurant_id' => $restaurant->id, 'modifier_group_id' => $group->id, 'position' => 0]);
        $this->actingAs($owner)->post(route('restaurant.pos.tables.store', [$restaurant, $table]), ['employee_id' => $employee->id, 'pin' => '4821'])->assertRedirect();
        $order = $restaurant->orders()->first();
        $round = $order->rounds()->first();
        $assignment = $product->modifierGroupAssignments()->first();
        $this->actingAs($owner)->post(route('restaurant.pos.rounds.lines.store', [$restaurant, $order, $round]), ['product_id' => $product->id, 'format_id' => $format->id, 'selections' => [$assignment->id => [$option->id => 1]], 'quantity' => 1, 'notes' => 'Sin sal'])->assertRedirect();
        $line = $order->lines()->first();
        $this->assertSame(2200, $line->line_total_minor);
        $this->assertSame('300 g', $line->snapshot['format']['name']);
        $this->assertSame('Poco hecho', $line->snapshot['modifiers'][0]['option_name']);
        $product->update(['name' => 'Entrecot cambiado', 'price_minor' => 9900]);
        $format->update(['price_minor' => 9999]);
        $line->refresh();
        $this->assertSame('Entrecot', $line->snapshot['product']['name']);
        $this->assertSame(2200, $line->line_total_minor);
    }

    public function test_foreign_product_and_foreign_table_are_rejected(): void
    {
        [$owner, $restaurant, $table, $employee] = $this->setupRestaurant();
        [$otherOwner, $other] = $this->restaurant();
        $otherProduct = $this->product($other, 'Otro', 100);
        $this->actingAs($owner)->post(route('restaurant.pos.tables.store', [$restaurant, $table]), ['employee_id' => $employee->id, 'pin' => '4821'])->assertRedirect();
        $order = $restaurant->orders()->first();
        $round = $order->rounds()->first();
        $this->actingAs($owner)->post(route('restaurant.pos.rounds.lines.store', [$restaurant, $order, $round]), ['product_id' => $otherProduct->id, 'quantity' => 1])->assertSessionHasErrors('product_id');
        $this->actingAs($owner)->get(route('restaurant.pos.tables.open', [$restaurant, $other->diningTables()->first() ?? DiningTable::create(['restaurant_id' => $other->id, 'zone_id' => $other->zones()->create(['name' => 'Otro'])->id, 'name' => 'Otra'])]))->assertNotFound();
    }

    public function test_reopening_account_creates_a_second_round_without_losing_the_first(): void
    {
        [$owner, $restaurant, $table, $employee] = $this->setupRestaurant();
        $product = $this->product($restaurant, 'Café', 220);
        $this->actingAs($owner)->post(route('restaurant.pos.tables.store', [$restaurant, $table]), ['employee_id' => $employee->id, 'pin' => '4821'])->assertRedirect();
        $order = $restaurant->orders()->first();
        $first = $order->rounds()->first();
        $this->actingAs($owner)->post(route('restaurant.pos.rounds.lines.store', [$restaurant, $order, $first]), ['product_id' => $product->id, 'quantity' => 2])->assertRedirect();
        $this->actingAs($owner)->post(route('restaurant.pos.rounds.submit', [$restaurant, $order, $first]))->assertRedirect();
        $this->actingAs($owner)->get(route('restaurant.pos.orders.show', [$restaurant, $order]))->assertOk();
        $second = $order->fresh()->rounds()->where('draft_slot', 1)->firstOrFail();
        $this->actingAs($owner)->post(route('restaurant.pos.rounds.lines.store', [$restaurant, $order, $second]), ['product_id' => $product->id, 'quantity' => 1])->assertRedirect();
        $this->actingAs($owner)->post(route('restaurant.pos.rounds.submit', [$restaurant, $order, $second]))->assertRedirect();
        $this->assertCount(2, $order->fresh()->rounds()->where('status', 'submitted')->get());
        $this->assertSame(660, $order->fresh()->total_minor);
    }

    public function test_account_can_be_transferred_and_discounted_without_changing_catalog(): void
    {
        [$owner, $restaurant, $table, $employee] = $this->setupRestaurant();
        $targetZone = $restaurant->zones()->create(['name' => 'Terraza', 'is_active' => true]);
        $target = $targetZone->diningTables()->create(['restaurant_id' => $restaurant->id, 'name' => 'Terraza 3', 'is_active' => true]);
        $product = $this->product($restaurant, 'Coca-Cola', 1000);
        $order = $this->openOrder($owner, $restaurant, $table, $employee);
        $round = $order->rounds()->first();
        $this->actingAs($owner)->post(route('restaurant.pos.rounds.lines.store', [$restaurant, $order, $round]), ['product_id' => $product->id, 'quantity' => 2])->assertRedirect();
        $this->actingAs($owner)->post(route('restaurant.pos.rounds.submit', [$restaurant, $order, $round]))->assertRedirect();
        $this->actingAs($owner)->post(route('restaurant.pos.orders.transfer.store', [$restaurant, $order]), ['dining_table_id' => $target->id])->assertRedirect();
        $this->assertSame($target->id, $order->fresh()->dining_table_id);
        $this->assertSame(2000, $order->fresh()->total_minor);
        $this->actingAs($owner)->post(route('restaurant.pos.orders.discount', [$restaurant, $order]), ['kind' => 'percentage', 'value' => 1000, 'reason' => 'Incidencia'])->assertRedirect();
        $this->assertSame(1800, $order->fresh()->total_minor);
        $this->assertSame(1000, $product->fresh()->price_minor);
        $this->assertDatabaseHas('order_events', ['order_id' => $order->id, 'type' => 'table_transferred']);
    }

    public function test_confirmed_line_can_be_partially_voided_with_audit(): void
    {
        [$owner, $restaurant, $table, $employee] = $this->setupRestaurant();
        $product = $this->product($restaurant, 'Caña', 500);
        $order = $this->openOrder($owner, $restaurant, $table, $employee);
        $round = $order->rounds()->first();
        $this->actingAs($owner)->post(route('restaurant.pos.rounds.lines.store', [$restaurant, $order, $round]), ['product_id' => $product->id, 'quantity' => 2]);
        $this->actingAs($owner)->post(route('restaurant.pos.rounds.submit', [$restaurant, $order, $round]));
        $line = $order->fresh()->lines()->first();
        $this->actingAs($owner)->post(route('restaurant.pos.orders.lines.void', [$restaurant, $order, $line]), ['quantity' => 1, 'reason' => 'Error al introducir'])->assertRedirect();
        $this->assertSame(1, $line->fresh()->voided_quantity);
        $this->assertSame(500, $order->fresh()->total_minor);
        $this->assertDatabaseHas('order_events', ['order_id' => $order->id, 'type' => 'line_voided']);
    }

    public function test_equal_split_rounds_cents_exactly_and_product_split_supports_partial_quantity(): void
    {
        [$owner, $restaurant, $table, $employee] = $this->setupRestaurant();
        $product = $this->product($restaurant, 'Caña', 1000);
        $order = $this->openOrder($owner, $restaurant, $table, $employee);
        $round = $order->rounds()->first();
        $this->actingAs($owner)->post(route('restaurant.pos.rounds.lines.store', [$restaurant, $order, $round]), ['product_id' => $product->id, 'quantity' => 10]);
        $this->actingAs($owner)->post(route('restaurant.pos.rounds.submit', [$restaurant, $order, $round]));
        $this->actingAs($owner)->post(route('restaurant.pos.orders.split.equal', [$restaurant, $order]), ['parts' => 3])->assertRedirect();
        $plan = $order->fresh()->splitPlans()->where('status', 'active')->firstOrFail();
        $this->assertSame(10000, $plan->parts()->sum('total_minor'));
        $this->assertSame([3333, 3333, 3334], $plan->parts()->pluck('total_minor')->all());
        $this->actingAs($owner)->post(route('restaurant.pos.splits.cancel', [$restaurant, $plan]))->assertRedirect();
        $this->actingAs($owner)->post(route('restaurant.pos.orders.split.products', [$restaurant, $order]), ['allocations' => [['1' => 3]]])->assertRedirect();
        $productPlan = $order->fresh()->splitPlans()->where('status', 'active')->firstOrFail();
        $this->assertSame(3000, $productPlan->parts()->first()->total_minor);
        $this->assertSame(3, $productPlan->parts()->first()->allocations()->first()->quantity);
    }

    public function test_allowed_manual_price_is_line_scoped_and_catalog_remains_unchanged(): void
    {
        [$owner, $restaurant, $table, $employee] = $this->setupRestaurant();
        $product = $this->product($restaurant, 'Especial', 2000);
        $product->update(['allows_manual_price' => true]);
        $order = $this->openOrder($owner, $restaurant, $table, $employee);
        $round = $order->rounds()->first();
        $this->actingAs($owner)->post(route('restaurant.pos.rounds.lines.store', [$restaurant, $order, $round]), ['product_id' => $product->id, 'quantity' => 1]);
        $line = $order->fresh()->lines()->first();
        $this->actingAs($owner)->post(route('restaurant.pos.orders.lines.manual-price', [$restaurant, $order, $line]), ['price_minor' => 1800, 'reason' => 'Evento'])->assertRedirect();
        $this->assertSame(1800, $order->fresh()->total_minor);
        $this->assertSame(2000, $product->fresh()->price_minor);
        $this->assertDatabaseHas('order_events', ['order_id' => $order->id, 'type' => 'manual_price_changed']);
    }

    private function setupRestaurant(): array
    {
        [$owner, $restaurant] = $this->restaurant();
        $zone = $restaurant->zones()->create(['name' => 'Salón', 'is_active' => true]);
        $table = $zone->diningTables()->create(['restaurant_id' => $restaurant->id, 'name' => 'Mesa 4', 'capacity' => 4, 'is_active' => true]);
        $role = OperationalRole::create(['restaurant_id' => $restaurant->id, 'name' => 'Camarero', 'code' => 'service']);
        $manager = OperationalRole::create(['restaurant_id' => $restaurant->id, 'name' => 'Encargado', 'code' => 'manager']);
        $employee = Employee::create(['restaurant_id' => $restaurant->id, 'first_name' => 'Ana', 'display_name' => 'ANA', 'is_active' => true, 'pin_hash' => Hash::make('4821'), 'pin_fingerprint' => AttendanceService::fingerprint($restaurant->id, '4821')]);
        $employee->operationalRoles()->attach($role);
        $employee->operationalRoles()->attach($manager);

        return [$owner, $restaurant, $table, $employee];
    }

    private function openOrder(User $owner, Restaurant $restaurant, DiningTable $table, Employee $employee)
    {
        $this->actingAs($owner)->post(route('restaurant.pos.tables.store', [$restaurant, $table]), ['employee_id' => $employee->id, 'pin' => '4821'])->assertRedirect();

        return $restaurant->orders()->firstOrFail();
    }

    private function product(Restaurant $restaurant, string $name, int $price): Product
    {
        $category = $restaurant->categories()->create(['name' => 'Carta', 'is_active' => true, 'available_dine_in' => true]);

        return $category->products()->create(['restaurant_id' => $restaurant->id, 'name' => $name, 'price_minor' => $price, 'vat_rate' => 10, 'is_active' => true, 'is_available' => true, 'available_dine_in' => true]);
    }

    private function restaurant(): array
    {
        $owner = User::factory()->create();
        $restaurant = Restaurant::factory()->create();
        $owner->restaurants()->attach($restaurant, ['role' => 'owner']);

        return [$owner, $restaurant];
    }
}
