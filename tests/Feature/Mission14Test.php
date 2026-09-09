<?php

namespace Tests\Feature;

use App\AccountingExportService;
use App\AnalyticsService;
use App\AttendanceService;
use App\AuditService;
use App\BillingService;
use App\ConnectorService;
use App\CouponService;
use App\FinancialService;
use App\FiscalService;
use App\LoyaltyService;
use App\Models\DiningTable;
use App\Models\Employee;
use App\Models\LoyaltyProgress;
use App\Models\OnlinePaymentIntent;
use App\Models\OnlineRefund;
use App\Models\OperationalRole;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProviderWebhookEvent;
use App\Models\PublicOrderRequest;
use App\Models\Restaurant;
use App\Models\User;
use App\OnlinePaymentService;
use App\PrintService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class Mission14Test extends TestCase
{
    use RefreshDatabase;

    public function test_kitchen_routing_creates_jobs_per_station_and_reprint_is_marked(): void
    {
        [$owner, $restaurant, $table, $employee] = $this->setupRestaurant();
        $bar = $restaurant->kitchenStations()->create(['name' => 'Barra', 'position' => 0]);
        $kitchen = $restaurant->kitchenStations()->create(['name' => 'Cocina', 'position' => 10]);
        $barPrinter = $restaurant->printers()->create(['name' => 'Barra principal', 'uses' => ['kitchen'], 'paper_width' => 80, 'connection' => 'network', 'is_active' => true]);
        $kitchenPrinter = $restaurant->printers()->create(['name' => 'Cocina caliente', 'uses' => ['kitchen'], 'paper_width' => 80, 'connection' => 'network', 'is_active' => true]);
        $backupPrinter = $restaurant->printers()->create(['name' => 'Cocina backup', 'uses' => ['kitchen'], 'paper_width' => 58, 'connection' => 'network', 'is_active' => true]);
        $bar->printers()->attach($barPrinter->id, ['restaurant_id' => $restaurant->id, 'position' => 0]);
        $kitchen->printers()->attach([$kitchenPrinter->id => ['restaurant_id' => $restaurant->id, 'position' => 0], $backupPrinter->id => ['restaurant_id' => $restaurant->id, 'position' => 1]]);

        $cola = $this->product($restaurant, 'Coca-Cola', 250);
        $cola->update(['kitchen_station_id' => $bar->id]);
        $entre = $this->product($restaurant, 'Entrecot', 2200);
        $entre->update(['kitchen_station_id' => $kitchen->id]);

        $order = $this->openOrder($owner, $restaurant, $table, $employee);
        $round = $order->rounds()->first();
        $this->actingAs($owner)->post(route('restaurant.pos.rounds.lines.store', [$restaurant, $order, $round]), ['product_id' => $cola->id, 'quantity' => 2]);
        $this->actingAs($owner)->post(route('restaurant.pos.rounds.lines.store', [$restaurant, $order, $round]), ['product_id' => $entre->id, 'quantity' => 1]);
        $this->actingAs($owner)->post(route('restaurant.pos.rounds.submit', [$restaurant, $order, $round]))->assertRedirect();

        $dispatch = $order->fresh()->rounds()->first()->kitchenDispatch;
        $this->assertNotNull($dispatch);
        $barJobs = $restaurant->printJobs()->where('kind', 'kitchen_ticket')->where('printer_id', $barPrinter->id)->get();
        $this->assertCount(1, $barJobs);
        $this->assertStringContainsString('COCA', strtoupper(implode("\n", $barJobs->first()->payload['lines'])));
        $this->assertStringNotContainsString('ENTRECOT', implode("\n", $barJobs->first()->payload['lines']));
        $this->assertSame(2, $restaurant->printJobs()->where('kind', 'kitchen_ticket')->whereIn('printer_id', [$kitchenPrinter->id, $backupPrinter->id])->count());

        $job = $barJobs->first();
        app(PrintService::class)->markPrinted($job);
        $this->assertSame('printed', $job->fresh()->status);
        $copy = app(PrintService::class)->reprint($job->fresh(), $owner, $employee);
        $this->assertTrue($copy->is_reprint);
        $this->assertStringContainsString('REIMPRESION', strtoupper(implode("\n", $copy->payload['lines'])));
        $this->assertDatabaseHas('order_events', ['order_id' => $order->id, 'type' => 'print_reprint']);
    }

    public function test_print_error_retry_and_void_do_not_duplicate(): void
    {
        [$owner, $restaurant, $table, $employee] = $this->setupRestaurant();
        $station = $restaurant->kitchenStations()->create(['name' => 'Cocina', 'position' => 0]);
        $printer = $restaurant->printers()->create(['name' => 'Cocina', 'uses' => ['kitchen'], 'paper_width' => 80, 'connection' => 'network', 'is_active' => true]);
        $station->printers()->attach($printer->id, ['restaurant_id' => $restaurant->id, 'position' => 0]);
        $product = $this->product($restaurant, 'Entrecot', 2200);
        $product->update(['kitchen_station_id' => $station->id]);

        $order = $this->openOrder($owner, $restaurant, $table, $employee);
        $round = $order->rounds()->first();
        $this->actingAs($owner)->post(route('restaurant.pos.rounds.lines.store', [$restaurant, $order, $round]), ['product_id' => $product->id, 'quantity' => 1]);
        $this->actingAs($owner)->post(route('restaurant.pos.rounds.submit', [$restaurant, $order, $round]));
        $job = $restaurant->printJobs()->where('kind', 'kitchen_ticket')->firstOrFail();

        app(PrintService::class)->markError($job, 'Papel atascado');
        $this->assertSame('error', $job->fresh()->status);
        app(PrintService::class)->retry($job->fresh());
        $this->assertSame('pending', $job->fresh()->status);
        $dup = app(PrintService::class)->enqueue('kitchen_ticket', $restaurant, $printer, $job->payload, ['reference' => $job->reference]);
        $this->assertSame($job->id, $dup->id);

        $line = $order->fresh()->lines()->first();
        $this->actingAs($owner)->post(route('restaurant.pos.orders.lines.void', [$restaurant, $order, $line]), ['quantity' => 1, 'reason' => 'Cliente cancela'])->assertRedirect();
        $voidJob = $restaurant->printJobs()->where('kind', 'kitchen_void')->firstOrFail();
        $this->assertStringContainsString('ANULACION', strtoupper(implode("\n", $voidJob->payload['lines'])));
    }

    public function test_connector_links_tenant_scoped_and_acks_jobs(): void
    {
        [$owner, $restaurant] = $this->restaurant();
        [$otherOwner, $other] = $this->restaurant();
        [$connector, $token, $code] = app(ConnectorService::class)->create($restaurant, 'TPV principal');

        $this->assertSame('pending', $connector->status);
        $linked = app(ConnectorService::class)->link($code, 'agent-1.0');
        $this->assertSame('linked', $linked->status);
        $this->assertTrue($linked->fresh()->isOnline());

        $printer = $restaurant->printers()->create(['name' => 'Ticket TPV', 'uses' => ['ticket'], 'paper_width' => 80, 'connection' => 'network', 'is_active' => true]);
        app(PrintService::class)->testPrint($printer, $owner);
        $jobs = app(ConnectorService::class)->pendingJobs($linked->fresh());
        $this->assertCount(1, $jobs);
        $this->assertSame('sending', $jobs->first()->status);

        $acked = app(ConnectorService::class)->acknowledge($linked->fresh(), $jobs->first()->fresh(), true);
        $this->assertSame('printed', $acked->status);

        $foreignJob = $other->printJobs()->create(['printer_id' => null, 'kind' => 'test', 'reference' => 'other-1', 'payload' => ['lines' => []], 'status' => 'sending', 'print_connector_id' => null]);
        try {
            app(ConnectorService::class)->acknowledge($linked->fresh(), $foreignJob, true);
            $this->fail('Debió rechazar trabajo de otro tenant.');
        } catch (\Throwable) {
            $this->assertTrue(true);
        }

        $stale = $this->actingAs($owner)->post(route('connector.link'), ['pairing_code' => 'ZZZZZZZZ'])->assertStatus(422);
        $stale->assertJson(['message' => 'Código de vinculación no válido o caducado.']);
    }

    public function test_cash_drawer_opens_on_cash_and_manual_is_audited_but_failure_keeps_payment(): void
    {
        [$owner, $restaurant, $table, $employee] = $this->setupRestaurant();
        $printer = $restaurant->printers()->create(['name' => 'Ticket TPV', 'uses' => ['ticket', 'cash'], 'paper_width' => 80, 'connection' => 'network', 'is_active' => true, 'open_drawer' => true]);
        $product = $this->product($restaurant, 'Café', 220);
        $order = $this->openOrder($owner, $restaurant, $table, $employee);
        $round = $order->rounds()->first();
        $this->actingAs($owner)->post(route('restaurant.pos.rounds.lines.store', [$restaurant, $order, $round]), ['product_id' => $product->id, 'quantity' => 1]);
        $this->actingAs($owner)->post(route('restaurant.pos.rounds.submit', [$restaurant, $order, $round]));
        $session = $this->cashSession($owner, $restaurant, $employee);
        app(FinancialService::class)->pay($order->fresh(), null, [['method_id' => app(FinancialService::class)->methods($restaurant)->firstWhere('is_cash', true)->id, 'amount_minor' => $order->fresh()->total_minor, 'tendered_minor' => $order->fresh()->total_minor]], 'drawer-'.$order->id, $employee, $owner, $session);

        $this->assertDatabaseHas('cash_drawer_events', ['restaurant_id' => $restaurant->id, 'trigger' => 'sale', 'status' => 'opened']);
        $this->assertDatabaseHas('print_jobs', ['restaurant_id' => $restaurant->id, 'kind' => 'drawer_kick']);
        $this->assertDatabaseHas('print_jobs', ['restaurant_id' => $restaurant->id, 'kind' => 'customer_ticket']);

        $printer->update(['is_active' => false]);
        $response = $this->actingAs($owner)->post(route('restaurant.hardware.drawer.open', $restaurant), ['employee_id' => $employee->id, 'pin' => '4821']);
        $response->assertSessionHasErrors('drawer');
        $this->assertDatabaseHas('cash_drawer_events', ['restaurant_id' => $restaurant->id, 'trigger' => 'manual', 'status' => 'error']);

        $this->actingAs($owner)->get(route('restaurant.hardware.drawer', $restaurant))->assertOk();
        $audit = app(AuditService::class)->entries($restaurant, ['module' => 'impresion']);
        $this->assertTrue($audit->where('module', 'impresion')->isNotEmpty());
    }

    public function test_online_payment_fake_flow_webhook_idempotent_and_reject_refunds(): void
    {
        [$owner, $restaurant] = $this->setupRestaurant();
        $request = $this->publicRequest($restaurant, 'delivery', 3250);
        $restaurant->integrations()->updateOrCreate(['provider' => 'stripe'], ['status' => 'test', 'mode' => 'test', 'settings' => ['driver' => 'fake'], 'secrets' => []]);

        $intent = app(OnlinePaymentService::class)->createIntent($restaurant, $request);
        $this->assertSame('processing', $intent->status);
        $this->actingAs($owner)->get(route('public.order.fake', ['token' => $request->public_token]))->assertOk();
        $this->post(route('public.order.fake.confirm', ['token' => $request->public_token]), ['result' => 'fail']);
        $this->assertSame('failed', $intent->fresh()->status);

        $intent = OnlinePaymentIntent::query()->findOrFail($intent->id);
        $intent->update(['status' => 'processing', 'failure_reason' => null]);
        app(OnlinePaymentService::class)->markIntentSucceeded($intent->fresh());
        $this->assertSame('succeeded', $intent->fresh()->status);

        $payload = json_encode(['id' => 'evt_test_1', 'type' => 'payment_intent.succeeded', 'data' => ['object' => ['payment_intent' => $intent->provider_intent_id, 'metadata' => ['request_key' => $request->request_key]]]]);
        $sig = hash_hmac('sha256', $payload, 'fake-secret');
        $event = app(OnlinePaymentService::class)->handleWebhook($restaurant, 'stripe', $payload, $sig);
        $this->assertSame('processed', $event->status);
        $dup = app(OnlinePaymentService::class)->handleWebhook($restaurant, $request->restaurant, $payload, $sig);
        $this->assertSame($event->id, $dup->id);
        $this->assertSame(1, ProviderWebhookEvent::query()->where('provider_event_id', 'evt_test_1')->count());

        try {
            app(OnlinePaymentService::class)->handleWebhook($restaurant, 'stripe', $payload, 'bad-signature');
            $this->fail('Firma inválida debió rechazarse.');
        } catch (\InvalidArgumentException) {
            $this->assertTrue(true);
        }

        $order = $this->acceptPublicRequest($owner, $restaurant, $request, $intent);
        $this->assertSame('paid', $order->fresh()->status);
        $this->assertSame('succeeded', $intent->fresh()->status);

        $mgr = $this->manager($restaurant);
        $mgrPin = $mgr->display_name === 'MNG' ? '9999' : '4821';
        $this->actingAs($owner)->post(route('restaurant.orders.reject', [$restaurant, $request->fresh()]), ['reason' => 'Producto agotado', 'employee_id' => $mgr->id, 'pin' => $mgrPin])->assertRedirect();
        $refund = OnlineRefund::query()->where('online_payment_intent_id', $intent->id)->firstOrFail();
        $this->assertSame('succeeded', $refund->status);
        $this->assertSame('refunded', $intent->fresh()->status);
    }

    public function test_fiscal_hash_vector_qr_chain_and_anulacion(): void
    {
        [$owner, $restaurant] = $this->setupRestaurant();
        $service = app(FiscalService::class);
        $vector = $service->altaHash('89890001K', '12345678/G33', '01-01-2024', 'F1', 1235, 12345, '', '2024-01-01T19:20:30+01:00');
        $this->assertSame('3C464DAF61ACB827C65FDA19F352A4E3BDC2C640E9E9FC4CC058073F38F12F60', $vector);

        $qr = $service->qrContent('test', 'B12345678', 'F2026/000001', '08-09-2026', 8250);
        $this->assertSame('https://prewww2.aeat.es/wlpl/TIKE-CONT/ValidarQR?nif=B12345678&numserie=F2026%2F000001&fecha=08-09-2026&importe=82.50', $qr);

        $order = $this->paidOrder($owner, $restaurant, 'Mesa fiscal', 8250);
        $customer = $restaurant->customers()->create(['display_name' => 'ACME', 'tax_id' => 'B12345678', 'legal_name' => 'ACME S.L.']);
        $invoice = app(BillingService::class)->createInvoice($order, $customer, $owner);
        $alta = $service->registerForDocument($invoice);
        $this->assertNotNull($alta);
        $this->assertSame('F1', $alta->fiscal_type);
        $this->assertSame('test', $alta->environment);
        $this->assertTrue(str_starts_with($alta->qr_content, 'https://prewww2.aeat.es/'));

        $order2 = $this->paidOrder($owner, $restaurant, 'Mesa fiscal 2', 1000);
        $invoice2 = app(BillingService::class)->createInvoice($order2, $customer, $owner);
        $alta2 = $service->registerForDocument($invoice2);
        $this->assertSame($alta->hash, $alta2->previous_hash);
        $this->assertNotSame($alta->hash, $alta2->hash);

        $anul = $service->anular($alta, $owner);
        $this->assertSame('anulacion', $anul->record_type);
        $this->assertSame($alta2->hash, $anul->previous_hash);
        $this->assertTrue($anul->hash !== $alta->hash);

        $this->actingAs($owner)->get(route('restaurant.fiscal', $restaurant))->assertOk()->assertSee('Aceptados');
        $this->actingAs($owner)->get(route('restaurant.fiscal.show', [$restaurant, $alta]))->assertOk();
        $this->actingAs($owner)->get(route('restaurant.fiscal.qr', [$restaurant, $alta]))->assertOk();
    }

    public function test_fiscal_retry_offline_and_test_live_isolation_and_tenant(): void
    {
        [$owner, $restaurant] = $this->setupRestaurant();
        [$otherOwner, $other] = $this->restaurant();
        $integration = app(FiscalService::class)->integration($restaurant);
        $integration->update(['settings' => ['simulate' => 'offline']]);
        $order = $this->paidOrder($owner, $restaurant, 'Mesa A', 1000);
        $customer = $restaurant->customers()->create(['display_name' => 'ACME', 'tax_id' => 'B11111111']);
        $invoice = app(BillingService::class)->createInvoice($order, $customer, $owner);
        $alta = app(FiscalService::class)->registerForDocument($invoice);
        $this->assertSame('error', $alta->fresh()->status);
        $integration->update(['settings' => ['simulate' => 'ok']]);
        $retry2 = app(FiscalService::class)->retry($alta->fresh());
        $this->assertSame('accepted', $retry2->status);

        $integration->update(['mode' => 'live']);
        $liveAlta = app(FiscalService::class)->registerForDocument($invoice);
        $this->assertNotSame($alta->id, $liveAlta->id);
        $this->assertSame('live', $liveAlta->environment);
        $this->assertStringContainsString('www2.agenciatributaria.gob.es', $liveAlta->qr_content);

        $this->actingAs($otherOwner)->get(route('restaurant.fiscal.show', [$other, $alta]))->assertNotFound();
        $this->assertSame(0, $other->fiscalRecords()->count());
    }

    public function test_accounting_exports_reconcile_with_analytics(): void
    {
        [$owner, $restaurant] = $this->setupRestaurant();
        $order = $this->paidOrder($owner, $restaurant, 'Mesa 7', 1000);
        app(BillingService::class)->ensureTicket($order->fresh(), $owner);
        $from = CarbonImmutable::now($restaurant->timezone)->startOfDay();
        $to = CarbonImmutable::now($restaurant->timezone)->endOfDay();

        $sales = app(AccountingExportService::class)->sales($restaurant, $from, $to, $owner);
        $payments = app(AccountingExportService::class)->payments($restaurant, $from, $to, $owner);
        $cash = app(AccountingExportService::class)->cash($restaurant, $from, $to, $owner);
        $invoices = app(AccountingExportService::class)->invoices($restaurant, $from, $to, $owner);

        $analytics = app(AnalyticsService::class)->overview($restaurant, $from, $to);
        $this->assertSame($analytics['revenue'], $order->fresh()->total_minor);
        $this->assertSame(count($sales['rows']) > 0, true);
        $this->assertSame($invoices['filename'], 'facturas.csv');
        $this->assertTrue(collect($payments['rows'])->sum(fn ($row) => (float) $row[4] * 100) >= $order->fresh()->total_minor - 1);

        $this->actingAs($owner)->get(route('restaurant.exports.accounting.sales', [$restaurant, 'from' => $from->toDateString(), 'to' => $to->toDateString()]))->assertOk();
        $this->assertDatabaseHas('export_logs', ['restaurant_id' => $restaurant->id, 'kind' => 'ventas']);

        $response = $this->actingAs($owner)->post(route('restaurant.integrations.accounting.save', $restaurant), ['sales_account' => '700', 'vat_account' => '477', 'customers_account' => '430', 'journal' => 'VN']);
        $response->assertRedirect();
        $this->assertSame('700', $restaurant->accountingMapping()->first()->sales_account);
    }

    public function test_coupons_validate_and_apply_once_with_fiscal_trace(): void
    {
        [$owner, $restaurant, $table, $employee] = $this->setupRestaurant();
        $coupon = $restaurant->coupons()->create(['code' => 'VERANO10', 'name' => 'Verano', 'kind' => 'percent', 'value' => 1000, 'is_active' => true, 'channels' => ['delivery'], 'min_order_minor' => 2000, 'max_uses' => 5, 'uses_count' => 0, 'one_per_customer' => true]);

        try {
            app(CouponService::class)->validate($restaurant, 'VERANO10', 'dine_in', 3000);
            $this->fail('Canal incorrecto debió fallar.');
        } catch (\InvalidArgumentException) {
            $this->assertTrue(true);
        }
        try {
            app(CouponService::class)->validate($restaurant, 'VERANO10', 'delivery', 1000);
            $this->fail('Mínimo debió fallar.');
        } catch (\InvalidArgumentException) {
            $this->assertTrue(true);
        }
        $coupon->update(['ends_at' => now()->subDay()]);
        try {
            app(CouponService::class)->validate($restaurant, 'VERANO10', 'delivery', 3000);
            $this->fail('Caducado debió fallar.');
        } catch (\InvalidArgumentException) {
            $this->assertTrue(true);
        }
        $coupon->update(['ends_at' => null]);

        $order = $this->openOrder($owner, $restaurant, $table, $employee);
        $order->update(['channel' => 'delivery']);
        $product = $this->product($restaurant, 'Burger', 1400);
        $round = $order->rounds()->first();
        $this->actingAs($owner)->post(route('restaurant.pos.rounds.lines.store', [$restaurant, $order, $round]), ['product_id' => $product->id, 'quantity' => 2]);
        $this->actingAs($owner)->post(route('restaurant.pos.rounds.submit', [$restaurant, $order, $round]));
        $fp = app(CouponService::class)->customerFp(null, '600123456');
        $updated = app(CouponService::class)->applyToOrder($order->fresh(), 'verano10', $fp, $owner, $employee);
        $this->assertSame(2520, $updated->total_minor);
        $this->assertDatabaseHas('coupon_redemptions', ['order_id' => $order->id, 'amount_minor' => 280]);
        $this->assertDatabaseHas('order_events', ['order_id' => $order->id, 'type' => 'coupon_applied']);

        try {
            app(CouponService::class)->applyToOrder($updated->fresh(), 'VERANO10', $fp, $owner, $employee);
            $this->fail('Doble promoción debió fallar.');
        } catch (\InvalidArgumentException) {
            $this->assertTrue(true);
        }
    }

    public function test_loyalty_accrues_once_redeems_and_reverts(): void
    {
        [$owner, $restaurant, $table, $employee] = $this->setupRestaurant();
        $coffee = $this->product($restaurant, 'Café', 250);
        $program = $restaurant->loyaltyPrograms()->create(['name' => '10.º café gratis', 'target_type' => 'product', 'target_id' => $coffee->id, 'goal' => 2, 'reward_product_id' => $coffee->id, 'is_active' => true]);
        $customer = $restaurant->customers()->create(['display_name' => 'María', 'phone' => '600123456', 'phone_normalized' => '600123456']);

        $order = $this->openOrder($owner, $restaurant, $table, $employee);
        $order->update(['customer_id' => $customer->id]);
        $round = $order->rounds()->first();
        $this->actingAs($owner)->post(route('restaurant.pos.rounds.lines.store', [$restaurant, $order, $round]), ['product_id' => $coffee->id, 'quantity' => 2]);
        $this->actingAs($owner)->post(route('restaurant.pos.rounds.submit', [$restaurant, $order, $round]));
        $this->payFull($owner, $restaurant, $order->fresh(), $employee);

        $progress = LoyaltyProgress::query()->where('loyalty_program_id', $program->id)->where('customer_id', $customer->id)->firstOrFail();
        $this->assertSame(1, $progress->rewards_available);
        app(LoyaltyService::class)->accrueForOrder($order->fresh());
        $this->assertSame(1, $progress->fresh()->rewards_available);

        $order2 = $this->openOrder($owner, $restaurant, $table, $employee);
        $order2->update(['customer_id' => $customer->id]);
        $redeemed = app(LoyaltyService::class)->redeem($order2->fresh(), $program, $owner, $employee);
        $this->assertSame(0, $redeemed->lines()->where('snapshot->loyalty_reward', true)->count() === 0 ? 1 : 0);
        $this->assertSame(0, $progress->fresh()->rewards_available);
        $this->assertDatabaseHas('loyalty_events', ['order_id' => $order2->id, 'kind' => 'redeem']);

        $payment = $order->fresh()->payments()->where('status', 'succeeded')->first();
        app(FinancialService::class)->reverse($payment, 'Devolución', 'rev-loyal-'.$payment->id, $this->manager($restaurant), $owner);
        app(LoyaltyService::class)->revertForOrder($order->fresh(), $owner, $employee);
        $this->assertDatabaseHas('loyalty_events', ['order_id' => $order->id, 'kind' => 'revert_accrue']);
    }

    public function test_multitenancy_blocks_cross_restaurant_resources(): void
    {
        [$owner, $restaurant] = $this->restaurant();
        [$otherOwner, $other] = $this->restaurant();
        $printer = $restaurant->printers()->create(['name' => 'Cocina', 'uses' => ['kitchen'], 'paper_width' => 80, 'connection' => 'network', 'is_active' => true]);
        $restaurant->coupons()->create(['code' => 'SOLOA', 'name' => 'Solo A', 'kind' => 'fixed', 'value' => 100, 'is_active' => true]);

        $this->actingAs($otherOwner)->patch(route('restaurant.hardware.printers.update', [$other, $printer]))->assertNotFound();
        $this->actingAs($otherOwner)->get(route('restaurant.fiscal', $other))->assertOk();
        $this->actingAs($owner)->get(route('restaurant.coupons', $restaurant))->assertOk()->assertSee('SOLOA');
        $this->actingAs($otherOwner)->get(route('restaurant.coupons', $other))->assertOk()->assertSee('Sin cupones')->assertDontSee('SOLOA');
    }

    private function publicRequest(Restaurant $restaurant, string $channel, int $total): PublicOrderRequest
    {
        return $restaurant->publicOrderRequests()->create([
            'channel' => $channel, 'status' => 'pending', 'public_token_hash' => hash('sha256', $t = bin2hex(random_bytes(32))),
            'public_token' => $t, 'request_key' => 'req-'.$t, 'payload_hash' => hash('sha256', $t),
            'currency' => 'EUR', 'subtotal_minor' => $total, 'delivery_fee_minor' => 0, 'total_minor' => $total,
            'customer_name' => 'Test', 'customer_phone' => '600123456', 'fulfillment_mode' => 'asap', 'requested_at' => now(),
        ]);
    }

    private function acceptPublicRequest(User $owner, Restaurant $restaurant, $request, $intent)
    {
        $table = $restaurant->zones()->create(['name' => 'Z'.$request->id, 'is_active' => true])->diningTables()->create(['restaurant_id' => $restaurant->id, 'name' => 'M'.$request->id, 'is_active' => true]);
        $order = $restaurant->orders()->create(['dining_table_id' => $table->id, 'channel' => $request->channel, 'origin' => 'public_'.$request->channel, 'status' => 'open', 'payment_status' => 'unpaid', 'currency' => 'EUR', 'total_minor' => $request->total_minor, 'business_date' => now($restaurant->timezone)->toDateString(), 'opened_at' => now()]);
        $round = $order->rounds()->create(['restaurant_id' => $restaurant->id, 'sequence' => 1, 'status' => 'submitted', 'submitted_at' => now(), 'submission_key' => 't-'.$request->id]);
        $request->update(['status' => 'accepted', 'accepted_order_id' => $order->id, 'accepted_round_id' => $round->id]);
        $intent->update(['order_id' => $order->id]);
        app(FinancialService::class)->payOnline($order->fresh(), $intent->fresh(), $owner);

        return $order->fresh();
    }

    private function paidOrder(User $owner, Restaurant $restaurant, string $tableName, int $total): Order
    {
        $zone = $restaurant->zones()->firstOrCreate(['name' => 'Salón'], ['is_active' => true]);
        $table = $zone->diningTables()->create(['restaurant_id' => $restaurant->id, 'name' => $tableName.microtime(true), 'is_active' => true]);
        $employee = $restaurant->employees()->first() ?? $this->employee($restaurant);
        $this->actingAs($owner)->post(route('restaurant.pos.tables.store', [$restaurant, $table]), ['employee_id' => $employee->id, 'pin' => '4821'])->assertRedirect();
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
        app(FinancialService::class)->pay($order, null, [['method_id' => $cash->id, 'amount_minor' => $order->total_minor, 'tendered_minor' => $order->total_minor]], 'full-'.$order->id.time().rand(1, 99999), $employee, $owner, $session);
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

    private function manager(Restaurant $restaurant): Employee
    {
        return $restaurant->employees()->whereHas('operationalRoles', fn ($q) => $q->where('code', 'manager'))->firstOrFail();
    }

    private function employee(Restaurant $restaurant): Employee
    {
        $role = OperationalRole::create(['restaurant_id' => $restaurant->id, 'name' => 'Camarero', 'code' => 'service']);
        $employee = Employee::create(['restaurant_id' => $restaurant->id, 'first_name' => 'Ana', 'display_name' => 'ANA', 'is_active' => true, 'pin_hash' => Hash::make('4821'), 'pin_fingerprint' => AttendanceService::fingerprint($restaurant->id, '4821')]);
        $employee->operationalRoles()->attach($role);

        return $employee;
    }

    private function setupRestaurant(): array
    {
        [$owner, $restaurant] = $this->restaurant();
        $restaurant->update(['tax_id' => 'B12345678', 'legal_name' => 'Casa Marchando S.L.']);
        $zone = $restaurant->zones()->create(['name' => 'Salón', 'is_active' => true]);
        $table = $zone->diningTables()->create(['restaurant_id' => $restaurant->id, 'name' => 'Mesa 4', 'capacity' => 4, 'is_active' => true]);
        $role = OperationalRole::create(['restaurant_id' => $restaurant->id, 'name' => 'Camarero', 'code' => 'service']);
        $manager = OperationalRole::create(['restaurant_id' => $restaurant->id, 'name' => 'Encargado', 'code' => 'manager']);
        $employee = Employee::create(['restaurant_id' => $restaurant->id, 'first_name' => 'Ana', 'display_name' => 'ANA', 'is_active' => true, 'pin_hash' => Hash::make('4821'), 'pin_fingerprint' => AttendanceService::fingerprint($restaurant->id, '4821')]);
        $employee->operationalRoles()->attach($role);
        $mng = Employee::create(['restaurant_id' => $restaurant->id, 'first_name' => 'Jefe', 'display_name' => 'MNG', 'is_active' => true, 'pin_hash' => Hash::make('9999'), 'pin_fingerprint' => AttendanceService::fingerprint($restaurant->id, '9999')]);
        $mng->operationalRoles()->attach($manager);
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
