<?php

namespace Tests\Feature;

use App\AnalyticsService;
use App\AttendanceService;
use App\BillingService;
use App\FinancialService;
use App\Models\DiningTable;
use App\Models\Employee;
use App\Models\OperationalRole;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\Product;
use App\Models\Restaurant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PilotGoldenPathTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_service_turn_reconciles_everywhere(): void
    {
        [$owner, $restaurant, $table, $employee] = $this->setupRestaurant();
        $cola = $this->product($restaurant, 'Coca-Cola', 250);
        $cola->update(['track_stock' => true, 'stock_quantity' => 50]);
        $burger = $this->product($restaurant, 'Burger', 1200);
        $burger->update(['track_stock' => true, 'stock_quantity' => 30]);

        // Abrir + primera ronda + segunda ronda (postres)
        $order = $this->openOrder($owner, $restaurant, $table, $employee);
        $round = $order->rounds()->first();
        $this->actingAs($owner)->post(route('restaurant.pos.rounds.lines.store', [$restaurant, $order, $round]), ['product_id' => $cola->id, 'quantity' => 2]);
        $this->actingAs($owner)->post(route('restaurant.pos.rounds.submit', [$restaurant, $order, $round]))->assertRedirect();
        $this->actingAs($owner)->get(route('restaurant.pos.orders.show', [$restaurant, $order]))->assertOk();
        $second = $order->fresh()->rounds()->where('draft_slot', 1)->firstOrFail();
        $this->actingAs($owner)->post(route('restaurant.pos.rounds.lines.store', [$restaurant, $order, $second]), ['product_id' => $burger->id, 'quantity' => 1]);
        $this->actingAs($owner)->post(route('restaurant.pos.rounds.submit', [$restaurant, $order, $second]))->assertRedirect();
        $this->assertSame(1700, $order->fresh()->total_minor);

        // Anulación parcial + descuento autorizado
        $line = $order->fresh()->lines()->where('product_id', $cola->id)->first();
        $this->actingAs($owner)->post(route('restaurant.pos.orders.lines.void', [$restaurant, $order, $line]), ['quantity' => 1, 'reason' => 'Se cayó'])->assertRedirect();
        $this->actingAs($owner)->post(route('restaurant.pos.orders.discount', [$restaurant, $order]), ['kind' => 'percentage', 'value' => 1000, 'reason' => 'Detalle casa'])->assertRedirect();
        $order = $order->fresh();
        $this->assertSame(1305, $order->total_minor); // 1450 subtotal - 145 dto (10 %)

        // Split en 2 + pagos mixtos (efectivo + tarjeta)
        $this->actingAs($owner)->post(route('restaurant.pos.orders.split.equal', [$restaurant, $order]), ['parts' => 2])->assertRedirect();
        $plan = $order->fresh()->splitPlans()->where('status', 'active')->firstOrFail();
        $this->assertSame($order->fresh()->total_minor, (int) $plan->parts()->sum('total_minor'));
        $methods = app(FinancialService::class)->methods($restaurant);
        $cash = $methods->firstWhere('is_cash', true);
        $card = $methods->firstWhere('is_cash', false);
        $parts = $plan->parts()->orderBy('sequence')->get();
        app(FinancialService::class)->pay($order->fresh(), $parts[0], [['method_id' => $cash->id, 'amount_minor' => $parts[0]->total_minor, 'tendered_minor' => $parts[0]->total_minor]], 'gp-1', $employee, $owner, $this->cashSession($owner, $restaurant, $employee));
        app(FinancialService::class)->pay($order->fresh(), $parts[1], [['method_id' => $card->id, 'amount_minor' => $parts[1]->total_minor]], 'gp-2', $employee, $owner, $this->cashSession($owner, $restaurant, $employee));
        $order = $order->fresh();
        $this->assertSame('paid', $order->status);
        $this->assertSame(0, $restaurant->activeTableOrders()->count());

        // Ticket + stock + analytics reconcilian
        $ticket = app(BillingService::class)->ensureTicket($order, $owner);
        $this->assertSame($order->total_minor, $ticket->total_minor);
        $from = CarbonImmutable::now($restaurant->timezone)->startOfDay();
        $to = CarbonImmutable::now($restaurant->timezone)->endOfDay();
        $analytics = app(AnalyticsService::class)->overview($restaurant, $from, $to);
        $this->assertSame($order->total_minor, $analytics['revenue']);
        $this->assertSame(1, $analytics['count']);
        $cashTotal = (int) $analytics['tenders']->where('is_cash', 1)->sum('total');
        $cardTotal = (int) $analytics['tenders']->where('is_cash', 0)->sum('total');
        $this->assertSame($order->total_minor, $cashTotal + $cardTotal);
        $this->assertDatabaseHas('stock_movements', ['order_id' => $order->id, 'type' => 'sale']);
        $this->assertSame(49, $cola->fresh()->stock_quantity); // 50 - 2 + 1 (anulación)
        $this->assertSame(29, $burger->fresh()->stock_quantity);
    }

    public function test_qr_public_flow_joins_table_account_and_kitchen(): void
    {
        [$owner, $restaurant, $table, $employee] = $this->setupRestaurant();
        $table->update(['qr_is_active' => true]);
        foreach (range(1, 7) as $weekday) {
            $restaurant->openingHours()->create(['context' => 'general', 'iso_weekday' => $weekday, 'start_minute' => 0, 'end_minute' => 1439]);
        }
        $product = $this->product($restaurant, 'Tapa', 500);

        $this->get(route('tables.resolve', ['token' => $table->qr_token]))->assertOk()->assertSee($table->name);
        $this->get(route('public.qr.product', [$table->qr_token, $product->id]))->assertOk();
        $this->post(route('public.qr.add', [$table->qr_token]), ['product_id' => $product->id, 'quantity' => 2])->assertRedirect();
        $this->get(route('public.qr.cart', [$table->qr_token]))->assertOk();
        $this->post(route('public.qr.submit', [$table->qr_token]), [
            'request_key' => 'qr-golden-1', 'name' => 'Ana', 'phone' => '600123456',
            'fulfillment_mode' => 'asap',
        ])->assertRedirect();
        // Doble envío con misma clave: una sola solicitud
        $this->post(route('public.qr.submit', [$table->qr_token]), [
            'request_key' => 'qr-golden-1', 'name' => 'Ana', 'phone' => '600123456',
            'fulfillment_mode' => 'asap',
        ]);
        $this->assertSame(1, $restaurant->publicOrderRequests()->where('request_key', 'qr-golden-1')->count());

        $pending = $restaurant->publicOrderRequests()->where('request_key', 'qr-golden-1')->firstOrFail();
        $this->actingAs($owner)->post(route('restaurant.orders.accept', [$restaurant, $pending]), ['employee_id' => $employee->id, 'pin' => '4821'])->assertRedirect();
        $order = $restaurant->orders()->where('id', $pending->fresh()->accepted_order_id)->firstOrFail();
        $this->assertSame($table->id, $order->dining_table_id);
        $this->assertSame(1000, $order->total_minor);
        $this->assertTrue($restaurant->kitchenItems()->whereHas('dispatch', fn ($q) => $q->where('order_id', $order->id))->exists());
    }

    public function test_same_table_concurrent_open_returns_single_account(): void
    {
        [$owner, $restaurant, $table, $employee] = $this->setupRestaurant();
        $this->actingAs($owner)->post(route('restaurant.pos.tables.store', [$restaurant, $table]), ['employee_id' => $employee->id, 'pin' => '4821'])->assertRedirect();
        $this->actingAs($owner)->post(route('restaurant.pos.tables.store', [$restaurant, $table]), ['employee_id' => $employee->id, 'pin' => '4821'])->assertRedirect();
        $this->assertSame(1, $restaurant->orders()->count());
        $this->assertSame(1, $restaurant->activeTableOrders()->count());
    }

    public function test_cash_close_reconciles_with_payments(): void
    {
        [$owner, $restaurant, $table, $employee] = $this->setupRestaurant();
        $order = $this->paidOrder($owner, $restaurant, $table, $employee, 1000);
        $session = $restaurant->cashSessions()->where('status', 'open')->firstOrFail();
        $expected = $session->opening_float_minor + (int) $session->movements()->where('type', 'sale')->sum('amount_minor');
        $closed = app(FinancialService::class)->closeSession($session, $expected, [], $employee, $owner);
        $this->assertSame('closed', $closed->status);
        $this->assertSame(0, $closed->difference_minor);
        $this->assertSame($order->total_minor, $expected);
    }

    public function test_load_simulation_service_rush_keeps_integrity(): void
    {
        [$owner, $restaurant] = $this->restaurant();
        $zone = $restaurant->zones()->create(['name' => 'Salón', 'is_active' => true]);
        $role = OperationalRole::create(['restaurant_id' => $restaurant->id, 'name' => 'Camarero', 'code' => 'service']);
        $manager = OperationalRole::create(['restaurant_id' => $restaurant->id, 'name' => 'Encargado', 'code' => 'manager']);
        $employee = Employee::create(['restaurant_id' => $restaurant->id, 'first_name' => 'Ana', 'display_name' => 'ANA', 'is_active' => true, 'pin_hash' => Hash::make('4821'), 'pin_fingerprint' => AttendanceService::fingerprint($restaurant->id, '4821')]);
        $employee->operationalRoles()->attach([$role->id, $manager->id]);
        $category = $restaurant->categories()->create(['name' => 'Carta', 'is_active' => true, 'available_dine_in' => true]);
        $products = collect(range(1, 10))->map(fn ($i) => $category->products()->create(['restaurant_id' => $restaurant->id, 'name' => 'Prod '.$i.' '.microtime(true).$i, 'price_minor' => 100 + $i * 10, 'vat_rate' => 10, 'is_active' => true, 'is_available' => true, 'available_dine_in' => true]));
        $tables = collect(range(1, 20))->map(fn ($i) => $zone->diningTables()->create(['restaurant_id' => $restaurant->id, 'name' => 'Mesa '.$i, 'capacity' => 4, 'is_active' => true]));

        $started = microtime(true);
        $expectedRevenue = 0;
        foreach ($tables as $table) {
            $this->actingAs($owner)->post(route('restaurant.pos.tables.store', [$restaurant, $table]), ['employee_id' => $employee->id, 'pin' => '4821']);
            $order = $restaurant->orders()->latest('id')->firstOrFail();
            $round = $order->rounds()->first();
            foreach ($products->take(5) as $product) {
                $this->actingAs($owner)->post(route('restaurant.pos.rounds.lines.store', [$restaurant, $order, $round]), ['product_id' => $product->id, 'quantity' => 1]);
            }
            $this->actingAs($owner)->post(route('restaurant.pos.rounds.submit', [$restaurant, $order, $round]));
            $expectedRevenue += $order->fresh()->total_minor;
            $this->payFull($owner, $restaurant, $order->fresh(), $employee);
        }
        $elapsed = microtime(true) - $started;

        $from = CarbonImmutable::now($restaurant->timezone)->startOfDay();
        $to = CarbonImmutable::now($restaurant->timezone)->endOfDay();
        $analytics = app(AnalyticsService::class)->overview($restaurant, $from, $to);
        $this->assertSame(20, $analytics['count']);
        $this->assertSame($expectedRevenue, $analytics['revenue']);
        $this->assertSame(20, $restaurant->orders()->where('status', 'paid')->count());
        $this->assertSame(100, OrderLine::query()->where('restaurant_id', $restaurant->id)->count()); // 20 cuentas x 5 líneas
        $this->assertLessThan(60, $elapsed, 'La simulación de 20 cuentas debería completarse en menos de 60s');
    }

    private function paidOrder(User $owner, Restaurant $restaurant, DiningTable $table, Employee $employee, int $total): Order
    {
        $this->actingAs($owner)->post(route('restaurant.pos.tables.store', [$restaurant, $table]), ['employee_id' => $employee->id, 'pin' => '4821']);
        $order = $restaurant->orders()->latest('id')->firstOrFail();
        $product = $this->product($restaurant, 'Prod '.$total, $total);
        $round = $order->rounds()->first();
        $this->actingAs($owner)->post(route('restaurant.pos.rounds.lines.store', [$restaurant, $order, $round]), ['product_id' => $product->id, 'quantity' => 1]);
        $this->actingAs($owner)->post(route('restaurant.pos.rounds.submit', [$restaurant, $order, $round]));
        $this->payFull($owner, $restaurant, $order->fresh(), $employee);

        return $order->fresh();
    }

    private function payFull(User $owner, Restaurant $restaurant, $order, Employee $employee): void
    {
        $cash = app(FinancialService::class)->methods($restaurant)->firstWhere('is_cash', true);
        $session = $this->cashSession($owner, $restaurant, $employee);
        app(FinancialService::class)->pay($order, null, [['method_id' => $cash->id, 'amount_minor' => $order->total_minor, 'tendered_minor' => $order->total_minor]], 'full-'.$order->id.'-'.microtime(true), $employee, $owner, $session);
    }

    private function cashSession(User $owner, Restaurant $restaurant, Employee $employee)
    {
        $register = app(FinancialService::class)->register($restaurant);
        $open = $restaurant->cashSessions()->where('cash_register_id', $register->id)->where('status', 'open')->first();
        if ($open) {
            return $open;
        }

        return app(FinancialService::class)->openSession($restaurant, $register, 0, $employee, $owner);
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

        return $restaurant->orders()->latest('id')->firstOrFail();
    }

    private function product(Restaurant $restaurant, string $name, int $price): Product
    {
        $category = $restaurant->categories()->firstOrCreate(['name' => 'Carta'], ['is_active' => true, 'available_dine_in' => true, 'available_takeaway' => true, 'available_delivery' => true]);

        return $category->products()->create(['restaurant_id' => $restaurant->id, 'name' => $name.$price.microtime(true), 'price_minor' => $price, 'vat_rate' => 10, 'is_active' => true, 'is_available' => true, 'available_dine_in' => true, 'available_takeaway' => true, 'available_delivery' => true]);
    }

    private function restaurant(): array
    {
        $owner = User::factory()->create();
        $restaurant = Restaurant::factory()->create();
        $owner->restaurants()->attach($restaurant, ['role' => 'owner']);

        return [$owner, $restaurant];
    }
}
