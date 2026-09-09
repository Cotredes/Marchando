<?php

namespace Tests\Feature;

use App\AttendanceService;
use App\BillingService;
use App\FinancialService;
use App\Models\DiningTable;
use App\Models\Employee;
use App\Models\OperationalRole;
use App\Models\Product;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SalesControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_history_lists_paid_orders_and_detail_uses_snapshots(): void
    {
        [$owner, $restaurant, $table, $employee] = $this->setupRestaurant();
        $product = $this->product($restaurant, 'Burger', 1200, 500);
        $order = $this->openOrder($owner, $restaurant, $table, $employee);
        $round = $order->rounds()->first();
        $this->actingAs($owner)->post(route('restaurant.pos.rounds.lines.store', [$restaurant, $order, $round]), ['product_id' => $product->id, 'quantity' => 2]);
        $this->actingAs($owner)->post(route('restaurant.pos.rounds.submit', [$restaurant, $order, $round]));
        $this->payFull($owner, $restaurant, $order->fresh(), $employee);

        $this->actingAs($owner)->get(route('restaurant.sales', $restaurant))->assertOk()->assertSee('#'.$order->id);
        $this->actingAs($owner)->get(route('restaurant.sales', [$restaurant, 'channel' => 'takeaway']))->assertOk()->assertDontSee('#'.$order->id);
        $product->update(['name' => 'Burger cambiada', 'price_minor' => 1400]);
        $this->actingAs($owner)->get(route('restaurant.sales.show', [$restaurant, $order]))->assertOk()->assertSee('Burger')->assertSee('24,00');
    }

    public function test_ticket_reference_is_stable_and_idempotent(): void
    {
        [$owner, $restaurant, $table, $employee] = $this->setupRestaurant();
        $order = $this->paidOrder($owner, $restaurant, $table, $employee, 'Coca-Cola', 250, 2);
        $first = app(BillingService::class)->ensureTicket($order, $owner);
        $second = app(BillingService::class)->ensureTicket($order, $owner);
        $this->assertSame($first->id, $second->id);
        $this->assertMatchesRegularExpression('/^T-\d{4}-\d{6}$/', $first->reference);
        $restaurant->update(['name' => 'Otro nombre']);
        $this->assertNotSame('Otro nombre', $first->fresh()->snapshot['restaurant']['name']);
    }

    public function test_invoice_numbering_is_sequential_and_rejects_duplicates(): void
    {
        [$owner, $restaurant, $table, $employee] = $this->setupRestaurant();
        $first = $this->paidOrder($owner, $restaurant, $table, $employee, 'Caña', 500, 2);
        $second = $this->paidOrder($owner, $restaurant, $table, $employee, 'Tapa', 800, 1);
        $customer = $restaurant->customers()->create(['display_name' => 'ACME', 'legal_name' => 'ACME RESTAURACIÓN S.L.', 'tax_id' => 'B12345678', 'fiscal_address' => 'Calle 1', 'city' => 'Madrid']);

        $invoiceOne = app(BillingService::class)->createInvoice($first, $customer, $owner);
        $invoiceTwo = app(BillingService::class)->createInvoice($second, $customer, $owner);
        $this->assertSame(1, $invoiceOne->number);
        $this->assertSame(2, $invoiceTwo->number);
        $this->assertNotSame($invoiceOne->reference, $invoiceTwo->reference);

        $this->actingAs($owner)->post(route('restaurant.invoices.store', [$restaurant, $first]), ['customer_id' => $customer->id])->assertRedirect();
        $this->assertSame(1, $first->saleDocuments()->where('kind', 'invoice')->count());
        $this->actingAs($owner)->get(route('restaurant.invoices.show', [$restaurant, $invoiceOne]))->assertOk()->assertSee($invoiceOne->reference);
    }

    public function test_invoice_math_and_fiscal_snapshot_are_immutable(): void
    {
        [$owner, $restaurant, $table, $employee] = $this->setupRestaurant();
        $order = $this->paidOrder($owner, $restaurant, $table, $employee, 'Entrecot', 2200, 1);
        $customer = $restaurant->customers()->create(['display_name' => 'ACME', 'legal_name' => 'ACME S.L.', 'tax_id' => 'B87654321', 'fiscal_address' => 'Calle Antigua 1', 'city' => 'Madrid']);
        $invoice = app(BillingService::class)->createInvoice($order, $customer, $owner);

        $this->assertSame(2200, $invoice->total_minor);
        $this->assertSame(2000, $invoice->tax_breakdown[0]['base_minor']);
        $this->assertSame(200, $invoice->tax_breakdown[0]['tax_minor']);

        $customer->update(['fiscal_address' => 'Calle Nueva 99']);
        $this->assertSame('Calle Antigua 1', $invoice->fresh()->snapshot['customer']['fiscal_address']);
        $this->assertSame('Calle Nueva 99', $customer->fresh()->fiscal_address);
    }

    public function test_stock_flows_through_sale_entry_adjust_and_void(): void
    {
        [$owner, $restaurant, $table, $employee] = $this->setupRestaurant();
        $product = $this->product($restaurant, 'Coca-Cola', 250, 100);
        $this->actingAs($owner)->patch(route('restaurant.stock.settings', [$restaurant, $product]), ['track_stock' => 1, 'stock_quantity' => 10, 'stock_minimum' => 3])->assertRedirect();
        $this->assertSame(10, $product->fresh()->stock_quantity);

        $order = $this->openOrder($owner, $restaurant, $table, $employee);
        $round = $order->rounds()->first();
        $this->actingAs($owner)->post(route('restaurant.pos.rounds.lines.store', [$restaurant, $order, $round]), ['product_id' => $product->id, 'quantity' => 3]);
        $this->actingAs($owner)->post(route('restaurant.pos.rounds.submit', [$restaurant, $order, $round]));
        $this->assertSame(7, $product->fresh()->stock_quantity);
        $this->assertDatabaseHas('stock_movements', ['product_id' => $product->id, 'type' => 'sale', 'quantity_delta' => -3]);

        $this->actingAs($owner)->post(route('restaurant.stock.entries', [$restaurant, $product]), ['quantity' => 24, 'reason' => 'Reposición'])->assertRedirect();
        $this->assertSame(31, $product->fresh()->stock_quantity);
        $this->actingAs($owner)->post(route('restaurant.stock.adjust', [$restaurant, $product]), ['stock_quantity' => 30, 'reason' => 'Conteo de cierre'])->assertRedirect();
        $this->assertSame(30, $product->fresh()->stock_quantity);

        $line = $order->fresh()->lines()->first();
        $this->actingAs($owner)->post(route('restaurant.pos.orders.lines.void', [$restaurant, $order, $line]), ['quantity' => 1, 'reason' => 'Error'])->assertRedirect();
        $this->assertSame(31, $product->fresh()->stock_quantity);
        $this->assertDatabaseHas('stock_movements', ['product_id' => $product->id, 'type' => 'sale_reversal', 'quantity_delta' => 1]);

        $this->actingAs($owner)->get(route('restaurant.stock', [$restaurant, 'status' => 'low']))->assertOk();
        $this->actingAs($owner)->get(route('restaurant.stock.show', [$restaurant, $product]))->assertOk()->assertSee('Reposición');
    }

    public function test_out_of_stock_blocks_sale_and_replenish_restores(): void
    {
        [$owner, $restaurant, $table, $employee] = $this->setupRestaurant();
        $product = $this->product($restaurant, 'Agua', 180, 60);
        $this->actingAs($owner)->patch(route('restaurant.stock.settings', [$restaurant, $product]), ['track_stock' => 1, 'stock_quantity' => 1])->assertRedirect();

        $order = $this->openOrder($owner, $restaurant, $table, $employee);
        $round = $order->rounds()->first();
        $this->actingAs($owner)->post(route('restaurant.pos.rounds.lines.store', [$restaurant, $order, $round]), ['product_id' => $product->id, 'quantity' => 1]);
        $this->actingAs($owner)->post(route('restaurant.pos.rounds.submit', [$restaurant, $order, $round]));
        $this->assertSame(0, $product->fresh()->stock_quantity);

        $this->actingAs($owner)->get(route('restaurant.pos.orders.show', [$restaurant, $order]))->assertOk()->assertSee('Agotado');
        $draft = $order->fresh()->rounds()->where('draft_slot', 1)->firstOrFail();
        $this->actingAs($owner)->post(route('restaurant.pos.rounds.lines.store', [$restaurant, $order, $draft]), ['product_id' => $product->id, 'quantity' => 1])->assertSessionHasErrors('product_id');

        $this->actingAs($owner)->post(route('restaurant.stock.entries', [$restaurant, $product]), ['quantity' => 12])->assertRedirect();
        $this->assertSame(12, $product->fresh()->stock_quantity);
    }

    public function test_analytics_reconciles_split_and_mixed_payments(): void
    {
        [$owner, $restaurant, $table, $employee] = $this->setupRestaurant();
        $product = $this->product($restaurant, 'Menú', 2500, 1000);
        $order = $this->openOrder($owner, $restaurant, $table, $employee);
        $round = $order->rounds()->first();
        $this->actingAs($owner)->post(route('restaurant.pos.rounds.lines.store', [$restaurant, $order, $round]), ['product_id' => $product->id, 'quantity' => 4]);
        $this->actingAs($owner)->post(route('restaurant.pos.rounds.submit', [$restaurant, $order, $round]));
        $this->actingAs($owner)->post(route('restaurant.pos.orders.split.equal', [$restaurant, $order]), ['parts' => 4])->assertRedirect();
        $plan = $order->fresh()->splitPlans()->where('status', 'active')->firstOrFail();
        foreach ($plan->parts as $index => $part) {
            app(FinancialService::class)->pay($order->fresh(), $part, $this->tenders($restaurant, $part->total_minor), 'split-'.$order->id.'-'.$index, $employee, $owner, $this->cashSession($owner, $restaurant, $employee));
        }

        $mixed = $this->paidOrderMixed($owner, $restaurant, $table, $employee);

        $response = $this->actingAs($owner)->get(route('restaurant.analytics', [$restaurant, 'period' => 'today']));
        $response->assertOk()->assertSee('100,00')->assertSee('80,00');
        $this->assertSame(18000, $restaurant->orders()->where('status', 'paid')->sum('total_minor'));
        $this->assertSame(2, $restaurant->orders()->where('status', 'paid')->count());
        $this->assertSame($mixed->id, $restaurant->orders()->where('status', 'paid')->orderByDesc('id')->first()->id);
    }

    public function test_audit_permissions_and_multitenancy(): void
    {
        [$owner, $restaurant, $table, $employee] = $this->setupRestaurant();
        $member = User::factory()->create();
        $restaurant->users()->attach($member, ['role' => 'member']);
        [$otherOwner, $other] = $this->restaurant();

        $this->actingAs($member)->get(route('restaurant.sales', $restaurant))->assertOk();
        $this->actingAs($member)->get(route('restaurant.analytics', $restaurant))->assertOk();
        $this->actingAs($member)->get(route('restaurant.audit', $restaurant))->assertForbidden();

        $order = $this->paidOrder($owner, $restaurant, $table, $employee, 'Caña', 500, 1);
        $restaurant->customers()->create(['display_name' => 'María López', 'phone' => '600123456', 'phone_normalized' => '600123456']);
        $this->actingAs($member)->post(route('restaurant.invoices.store', [$restaurant, $order]), [])->assertForbidden();
        $this->actingAs($otherOwner)->get(route('restaurant.sales.show', [$other, $order]))->assertNotFound();
        $this->actingAs($otherOwner)->get(route('restaurant.customers', $other))->assertOk()->assertDontSee('María');

        $this->actingAs($owner)->get(route('restaurant.audit', $restaurant))->assertOk()->assertSee('Cerró y cobró');
    }

    private function paidOrder(User $owner, Restaurant $restaurant, DiningTable $table, Employee $employee, string $name, int $price, int $qty)
    {
        $order = $this->openOrder($owner, $restaurant, $table, $employee);
        $round = $order->rounds()->first();
        $product = $this->product($restaurant, $name, $price, (int) ($price / 2));
        $this->actingAs($owner)->post(route('restaurant.pos.rounds.lines.store', [$restaurant, $order, $round]), ['product_id' => $product->id, 'quantity' => $qty]);
        $this->actingAs($owner)->post(route('restaurant.pos.rounds.submit', [$restaurant, $order, $round]));
        $this->payFull($owner, $restaurant, $order->fresh(), $employee);

        return $order->fresh();
    }

    private function paidOrderMixed(User $owner, Restaurant $restaurant, DiningTable $table, Employee $employee)
    {
        $zone = $restaurant->zones()->where('name', 'Barra')->first() ?? $restaurant->zones()->create(['name' => 'Barra', 'is_active' => true]);
        $second = $zone->diningTables()->create(['restaurant_id' => $restaurant->id, 'name' => 'Mesa 9', 'capacity' => 2, 'is_active' => true]);
        $order = $this->openOrder($owner, $restaurant, $second, $employee);
        $round = $order->rounds()->first();
        $product = $this->product($restaurant, 'Mixto', 8000, 3000);
        $this->actingAs($owner)->post(route('restaurant.pos.rounds.lines.store', [$restaurant, $order, $round]), ['product_id' => $product->id, 'quantity' => 1]);
        $this->actingAs($owner)->post(route('restaurant.pos.rounds.submit', [$restaurant, $order, $round]));
        $methods = app(FinancialService::class)->methods($restaurant);
        $cash = $methods->firstWhere('is_cash', true);
        $card = $methods->firstWhere('is_cash', false);
        app(FinancialService::class)->pay($order->fresh(), null, [
            ['method_id' => $cash->id, 'amount_minor' => 4000, 'tendered_minor' => 4000],
            ['method_id' => $card->id, 'amount_minor' => 4000],
        ], 'mixed-'.$order->id, $employee, $owner, $this->cashSession($owner, $restaurant, $employee));

        return $order->fresh();
    }

    private function payFull(User $owner, Restaurant $restaurant, $order, Employee $employee): void
    {
        app(FinancialService::class)->pay($order, null, $this->tenders($restaurant, $order->total_minor), 'full-'.$order->id, $employee, $owner, $this->cashSession($owner, $restaurant, $employee));
    }

    private function tenders(Restaurant $restaurant, int $amount): array
    {
        $cash = app(FinancialService::class)->methods($restaurant)->firstWhere('is_cash', true);

        return [['method_id' => $cash->id, 'amount_minor' => $amount, 'tendered_minor' => $amount]];
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

    private function product(Restaurant $restaurant, string $name, int $price, int $cost): Product
    {
        $category = $restaurant->categories()->firstOrCreate(['name' => 'Carta'], ['is_active' => true, 'available_dine_in' => true]);

        return $category->products()->create(['restaurant_id' => $restaurant->id, 'name' => $name.$price.$cost.microtime(true), 'price_minor' => $price, 'cost_minor' => $cost, 'vat_rate' => 10, 'is_active' => true, 'is_available' => true, 'available_dine_in' => true]);
    }

    private function restaurant(): array
    {
        $owner = User::factory()->create();
        $restaurant = Restaurant::factory()->create();
        $owner->restaurants()->attach($restaurant, ['role' => 'owner']);

        return [$owner, $restaurant];
    }
}
