<?php

use App\Http\Controllers\AccountingController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\RestaurantController as AdminRestaurantController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\CashController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ClockController;
use App\Http\Controllers\ConnectorApiController;
use App\Http\Controllers\CouponController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DiningTableBatchController;
use App\Http\Controllers\DiningTableController;
use App\Http\Controllers\DiningTableQrController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\FiscalController;
use App\Http\Controllers\HardwareController;
use App\Http\Controllers\IntegrationController;
use App\Http\Controllers\KitchenController;
use App\Http\Controllers\LoyaltyController;
use App\Http\Controllers\ModifierGroupController;
use App\Http\Controllers\ModifierOptionController;
use App\Http\Controllers\OnlinePaymentController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PosAdvancedController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductFormatController;
use App\Http\Controllers\ProductModifierGroupController;
use App\Http\Controllers\PublicOrderAdminController;
use App\Http\Controllers\PublicOrderingController;
use App\Http\Controllers\RestaurantFloorPlanController;
use App\Http\Controllers\RestaurantSettingsController;
use App\Http\Controllers\SalesController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\ZoneController;
use App\Http\Controllers\ZonePrintController;
use App\Models\Restaurant;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (! auth()->check()) {
        return redirect()->route('login');
    }

    return redirect()->route('app.index');
})->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('login.store');
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::get('/t/{token}', [PublicOrderingController::class, 'qr'])
    ->where('token', '[a-f0-9]{64}')
    ->middleware('throttle:60,1')
    ->name('tables.resolve');

Route::get('/t/{token}/product/{product}', [PublicOrderingController::class, 'qrProduct'])->where('token', '[a-f0-9]{64}')->name('public.qr.product');
Route::post('/t/{token}/cart', [PublicOrderingController::class, 'qrAdd'])->middleware('throttle:30,1')->where('token', '[a-f0-9]{64}')->name('public.qr.add');
Route::get('/t/{token}/cart', [PublicOrderingController::class, 'qrCart'])->where('token', '[a-f0-9]{64}')->name('public.qr.cart');
Route::get('/t/{token}/checkout', [PublicOrderingController::class, 'qrCheckout'])->where('token', '[a-f0-9]{64}')->name('public.qr.checkout');
Route::post('/t/{token}/checkout', [PublicOrderingController::class, 'qrSubmit'])->middleware('throttle:10,1')->where('token', '[a-f0-9]{64}')->name('public.qr.submit');
Route::get('/public/{restaurant:slug}/{channel}', [PublicOrderingController::class, 'catalog'])->whereIn('channel', ['takeaway', 'delivery'])->name('public.catalog');
Route::get('/public/{restaurant:slug}/{channel}/products/{product}', [PublicOrderingController::class, 'product'])->whereIn('channel', ['takeaway', 'delivery'])->name('public.product');
Route::post('/public/{restaurant:slug}/{channel}/cart', [PublicOrderingController::class, 'add'])->middleware('throttle:30,1')->whereIn('channel', ['takeaway', 'delivery'])->name('public.add');
Route::get('/public/{restaurant:slug}/{channel}/cart', [PublicOrderingController::class, 'cart'])->whereIn('channel', ['takeaway', 'delivery'])->name('public.cart');
Route::get('/public/{restaurant:slug}/{channel}/checkout', [PublicOrderingController::class, 'checkout'])->whereIn('channel', ['takeaway', 'delivery'])->name('public.checkout');
Route::post('/public/{restaurant:slug}/{channel}/checkout', [PublicOrderingController::class, 'submit'])->middleware('throttle:10,1')->whereIn('channel', ['takeaway', 'delivery'])->name('public.submit');
Route::get('/order-tracking/{token}', [PublicOrderingController::class, 'track'])->middleware('throttle:30,1')->name('public.order.track');
Route::get('/order-tracking/{token}/snapshot', [PublicOrderingController::class, 'snapshot'])->middleware('throttle:60,1')->name('public.order.snapshot');
Route::post('/order-tracking/{token}/pay', [OnlinePaymentController::class, 'create'])->middleware('throttle:10,1')->name('public.order.pay');
Route::get('/order-tracking/{token}/pay/fake', [OnlinePaymentController::class, 'fake'])->middleware('throttle:30,1')->name('public.order.fake');
Route::post('/order-tracking/{token}/pay/fake', [OnlinePaymentController::class, 'fakeConfirm'])->middleware('throttle:10,1')->name('public.order.fake.confirm');
Route::get('/order-tracking/{token}/paid', [OnlinePaymentController::class, 'paidReturn'])->name('public.order.online.return');
Route::post('/webhooks/stripe/{restaurant:slug}', [OnlinePaymentController::class, 'webhook'])->middleware('throttle:60,1')->name('webhooks.stripe');
Route::post('/connector/link', [ConnectorApiController::class, 'link'])->middleware('throttle:10,1')->name('connector.link');
Route::post('/connector/heartbeat', [ConnectorApiController::class, 'heartbeat'])->middleware('throttle:60,1')->name('connector.heartbeat');
Route::get('/connector/jobs', [ConnectorApiController::class, 'jobs'])->middleware('throttle:60,1')->name('connector.jobs');
Route::post('/connector/jobs/{job}', [ConnectorApiController::class, 'ack'])->middleware('throttle:60,1')->name('connector.ack');

Route::middleware('auth')->prefix('app')->name('app.')->group(function () {
    Route::get('/', function () {
        $user = auth()->user();

        $remembered = Restaurant::query()->find(session('current_restaurant_id'));
        if ($remembered && ($user->isPlatformOwner() || ($remembered->is_active && $user->restaurants()->whereKey($remembered->getKey())->exists()))) {
            return redirect()->route('restaurant.dashboard', $remembered);
        }

        $restaurant = $user->isPlatformOwner()
            ? Restaurant::query()->orderBy('name')->first()
            : $user->restaurants()->orderBy('name')->first();

        if (! $restaurant instanceof Restaurant) {
            if ($user->isPlatformOwner()) {
                return redirect()->route('admin.dashboard');
            }

            return view('restaurants.empty');
        }

        return redirect()->route('restaurant.dashboard', $restaurant);
    })->name('index');
});

Route::middleware(['auth', 'platform.owner'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');

        Route::get('/restaurantes', [AdminRestaurantController::class, 'index'])->name('restaurants.index');
        Route::get('/restaurantes/crear', [AdminRestaurantController::class, 'create'])->name('restaurants.create');
        Route::post('/restaurantes', [AdminRestaurantController::class, 'store'])->name('restaurants.store');
        Route::get('/restaurantes/{restaurant}/editar', [AdminRestaurantController::class, 'edit'])->name('restaurants.edit');
        Route::patch('/restaurantes/{restaurant}', [AdminRestaurantController::class, 'update'])->name('restaurants.update');
        Route::post('/restaurantes/{restaurant}/estado', [AdminRestaurantController::class, 'toggle'])->name('restaurants.toggle');
        Route::get('/restaurantes/{restaurant}/entrar', [AdminRestaurantController::class, 'enter'])->name('restaurants.enter');

        Route::get('/usuarios', [AdminUserController::class, 'index'])->name('users.index');
        Route::get('/usuarios/crear', [AdminUserController::class, 'create'])->name('users.create');
        Route::post('/usuarios', [AdminUserController::class, 'store'])->name('users.store');
        Route::get('/usuarios/{user}/editar', [AdminUserController::class, 'edit'])->name('users.edit');
        Route::patch('/usuarios/{user}', [AdminUserController::class, 'update'])->name('users.update');
        Route::post('/usuarios/{user}/accesos', [AdminUserController::class, 'attachMembership'])->name('users.memberships.attach');
        Route::patch('/usuarios/{user}/accesos/{restaurant}', [AdminUserController::class, 'updateMembership'])->name('users.memberships.update');
        Route::delete('/usuarios/{user}/accesos/{restaurant}', [AdminUserController::class, 'detachMembership'])->name('users.memberships.detach');
        Route::post('/usuarios/{user}/estado', [AdminUserController::class, 'toggleActive'])->name('users.toggle');
    });

Route::middleware(['auth', 'restaurant.member'])
    ->prefix('app/{restaurant:slug}')
    ->name('restaurant.')
    ->scopeBindings()
    ->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        foreach (DashboardController::modules() as $module => $details) {
            if ($module === 'orders') {
                Route::get('/orders', [PublicOrderAdminController::class, 'index'])->name('orders');
                Route::post('/orders/{publicOrderRequest}/accept', [PublicOrderAdminController::class, 'accept'])->name('orders.accept');
                Route::post('/orders/{publicOrderRequest}/reject', [PublicOrderAdminController::class, 'reject'])->name('orders.reject');
                Route::post('/orders/intents/{onlineIntent}/refund', [OnlinePaymentController::class, 'refund'])->name('orders.intents.refund');

                continue;
            }
            if ($module === 'restaurant') {
                Route::get('/restaurant', [RestaurantFloorPlanController::class, 'index'])->name('restaurant');
                Route::get('/restaurant/manage', [RestaurantFloorPlanController::class, 'manage'])->name('restaurant.manage');
                Route::post('/restaurant/zones', [ZoneController::class, 'store'])->name('restaurant.zones.store');
                Route::patch('/restaurant/zones/{zone}', [ZoneController::class, 'update'])->name('restaurant.zones.update');
                Route::delete('/restaurant/zones/{zone}', [ZoneController::class, 'destroy'])->name('restaurant.zones.destroy');
                Route::patch('/restaurant/zones/{zone}/move/{direction}', [ZoneController::class, 'move'])->name('restaurant.zones.move');
                Route::post('/restaurant/zones/{zone}/tables', [DiningTableController::class, 'store'])->name('restaurant.tables.store');
                Route::post('/restaurant/zones/{zone}/tables/batch', [DiningTableBatchController::class, 'store'])->name('restaurant.tables.batch-store');
                Route::patch('/restaurant/zones/{zone}/tables/{diningTable}', [DiningTableController::class, 'update'])->name('restaurant.tables.update');
                Route::delete('/restaurant/zones/{zone}/tables/{diningTable}', [DiningTableController::class, 'destroy'])->name('restaurant.tables.destroy');
                Route::patch('/restaurant/zones/{zone}/tables/{diningTable}/move/{direction}', [DiningTableController::class, 'move'])->name('restaurant.tables.move');
                Route::patch('/restaurant/zones/{zone}/tables/{diningTable}/transfer', [DiningTableController::class, 'transfer'])->name('restaurant.tables.transfer');
                Route::patch('/restaurant/zones/{zone}/tables/{diningTable}/qr/activate', [DiningTableQrController::class, 'activate'])->name('restaurant.tables.qr.activate');
                Route::patch('/restaurant/zones/{zone}/tables/{diningTable}/qr/revoke', [DiningTableQrController::class, 'revoke'])->name('restaurant.tables.qr.revoke');
                Route::post('/restaurant/zones/{zone}/tables/{diningTable}/qr/regenerate', [DiningTableQrController::class, 'regenerate'])->name('restaurant.tables.qr.regenerate');
                Route::get('/restaurant/zones/{zone}/tables/{diningTable}/qr.svg', [DiningTableQrController::class, 'svg'])->name('restaurant.tables.qr.svg');
                Route::get('/restaurant/zones/{zone}/qr-sheet', ZonePrintController::class)->name('restaurant.zones.qr-sheet');

                continue;
            }

            if ($module === 'menu') {
                Route::get('/menu', [CatalogController::class, 'index'])->name('menu');
                Route::get('/menu/categories', [CatalogController::class, 'categories'])->name('menu.categories.index');
                Route::get('/menu/categories/create', [CategoryController::class, 'create'])->name('menu.categories.create');
                Route::post('/menu/categories', [CategoryController::class, 'store'])->name('menu.categories.store');
                Route::get('/menu/categories/{category}/edit', [CategoryController::class, 'edit'])->name('menu.categories.edit');
                Route::patch('/menu/categories/{category}', [CategoryController::class, 'update'])->name('menu.categories.update');
                Route::delete('/menu/categories/{category}', [CategoryController::class, 'destroy'])->name('menu.categories.destroy');
                Route::patch('/menu/categories/{category}/move/{direction}', [CatalogController::class, 'moveCategory'])->name('menu.categories.move');
                Route::get('/menu/products/create', [ProductController::class, 'create'])->name('menu.products.create');
                Route::post('/menu/products', [ProductController::class, 'store'])->name('menu.products.store');
                Route::get('/menu/products/{product}/edit', [ProductController::class, 'edit'])->name('menu.products.edit');
                Route::patch('/menu/products/{product}', [ProductController::class, 'update'])->name('menu.products.update');
                Route::delete('/menu/products/{product}', [ProductController::class, 'destroy'])->name('menu.products.destroy');
                Route::patch('/menu/products/{product}/availability', [ProductController::class, 'availability'])->name('menu.products.availability');
                Route::patch('/menu/products/{product}/move/{direction}', [CatalogController::class, 'moveProduct'])->name('menu.products.move');
                Route::get('/menu/products/{product}/formats/create', [ProductFormatController::class, 'create'])->name('menu.products.formats.create');
                Route::post('/menu/products/{product}/formats', [ProductFormatController::class, 'store'])->name('menu.products.formats.store');
                Route::get('/menu/products/{product}/formats/{format}/edit', [ProductFormatController::class, 'edit'])->name('menu.products.formats.edit');
                Route::patch('/menu/products/{product}/formats/{format}', [ProductFormatController::class, 'update'])->name('menu.products.formats.update');
                Route::delete('/menu/products/{product}/formats/{format}', [ProductFormatController::class, 'destroy'])->name('menu.products.formats.destroy');
                Route::patch('/menu/products/{product}/formats/{format}/move/{direction}', [ProductFormatController::class, 'move'])->name('menu.products.formats.move');
                Route::patch('/menu/products/{product}/formats/{format}/default', [ProductFormatController::class, 'makeDefault'])->name('menu.products.formats.default');
                Route::post('/menu/products/{product}/modifier-groups', [ProductModifierGroupController::class, 'store'])->name('menu.products.modifier-groups.store');
                Route::delete('/menu/products/{product}/modifier-groups/{modifierGroup}', [ProductModifierGroupController::class, 'destroy'])->name('menu.products.modifier-groups.destroy');
                Route::patch('/menu/products/{product}/modifier-groups/{modifierGroup}/move/{direction}', [ProductModifierGroupController::class, 'move'])->name('menu.products.modifier-groups.move');
                Route::post('/menu/products/{product}/modifier-groups/{modifierGroup}/duplicate', [ProductModifierGroupController::class, 'duplicate'])->name('menu.products.modifier-groups.duplicate');
                Route::get('/menu/modifier-groups', [ModifierGroupController::class, 'index'])->name('menu.modifier-groups.index');
                Route::get('/menu/modifier-groups/create', [ModifierGroupController::class, 'create'])->name('menu.modifier-groups.create');
                Route::post('/menu/modifier-groups', [ModifierGroupController::class, 'store'])->name('menu.modifier-groups.store');
                Route::get('/menu/modifier-groups/{modifierGroup}/edit', [ModifierGroupController::class, 'edit'])->name('menu.modifier-groups.edit');
                Route::patch('/menu/modifier-groups/{modifierGroup}', [ModifierGroupController::class, 'update'])->name('menu.modifier-groups.update');
                Route::delete('/menu/modifier-groups/{modifierGroup}', [ModifierGroupController::class, 'destroy'])->name('menu.modifier-groups.destroy');
                Route::post('/menu/modifier-groups/{modifierGroup}/duplicate', [ModifierGroupController::class, 'duplicate'])->name('menu.modifier-groups.duplicate');
                Route::get('/menu/modifier-groups/{modifierGroup}/options/create', [ModifierOptionController::class, 'create'])->name('menu.modifier-groups.options.create');
                Route::post('/menu/modifier-groups/{modifierGroup}/options', [ModifierOptionController::class, 'store'])->name('menu.modifier-groups.options.store');
                Route::get('/menu/modifier-groups/{modifierGroup}/options/{modifierOption}/edit', [ModifierOptionController::class, 'edit'])->name('menu.modifier-groups.options.edit');
                Route::patch('/menu/modifier-groups/{modifierGroup}/options/{modifierOption}', [ModifierOptionController::class, 'update'])->name('menu.modifier-groups.options.update');
                Route::delete('/menu/modifier-groups/{modifierGroup}/options/{modifierOption}', [ModifierOptionController::class, 'destroy'])->name('menu.modifier-groups.options.destroy');
                Route::patch('/menu/modifier-groups/{modifierGroup}/options/{modifierOption}/move/{direction}', [ModifierOptionController::class, 'move'])->name('menu.modifier-groups.options.move');

                continue;
            }

            if ($module === 'settings') {
                Route::get('/settings', [RestaurantSettingsController::class, 'edit'])->name('settings');
                Route::patch('/settings/details', [RestaurantSettingsController::class, 'updateDetails'])->name('settings.details.update');
                Route::patch('/settings/channels', [RestaurantSettingsController::class, 'updateChannels'])->name('settings.channels.update');
                Route::put('/settings/hours', [RestaurantSettingsController::class, 'updateHours'])->name('settings.hours.update');

                continue;
            }

            if ($module === 'staff') {
                Route::get('/staff', [StaffController::class, 'index'])->name('staff');
                Route::get('/staff/employees', [EmployeeController::class, 'index'])->name('staff.employees.index');
                Route::get('/staff/employees/create', [EmployeeController::class, 'create'])->name('staff.employees.create');
                Route::post('/staff/employees', [EmployeeController::class, 'store'])->name('staff.employees.store');
                Route::get('/staff/employees/{employee}', [EmployeeController::class, 'show'])->name('staff.employees.show');
                Route::get('/staff/employees/{employee}/edit', [EmployeeController::class, 'edit'])->name('staff.employees.edit');
                Route::patch('/staff/employees/{employee}', [EmployeeController::class, 'update'])->name('staff.employees.update');
                Route::get('/staff/attendance/{interval}/edit', [StaffController::class, 'edit'])->name('staff.attendance.edit');
                Route::post('/staff/attendance', [StaffController::class, 'add'])->name('staff.attendance.add');
                Route::patch('/staff/attendance/{interval}', [StaffController::class, 'correct'])->name('staff.attendance.correct');
                Route::get('/staff/clock', [ClockController::class, 'index'])->name('staff.clock');
                Route::post('/staff/clock/identify', [ClockController::class, 'identify'])->middleware('throttle:10,1')->name('staff.clock.identify');
                Route::post('/staff/clock/punch', [ClockController::class, 'punch'])->middleware('throttle:30,1')->name('staff.clock.punch');
                Route::post('/staff/clock/forget', [ClockController::class, 'forget'])->name('staff.clock.forget');

                continue;
            }

            if ($module === 'pos') {
                Route::get('/pos', [PosController::class, 'index'])->name('pos');
                Route::get('/pos/cash', [CashController::class, 'index'])->name('pos.cash');
                Route::post('/pos/cash/open', [CashController::class, 'open'])->middleware('throttle:10,1')->name('pos.cash.open');
                Route::post('/pos/cash/close', [CashController::class, 'close'])->name('pos.cash.close');
                Route::get('/pos/tables/{diningTable}/open', [PosController::class, 'openForm'])->name('pos.tables.open');
                Route::post('/pos/tables/{diningTable}/open', [PosController::class, 'open'])->middleware('throttle:20,1')->name('pos.tables.store');
                Route::get('/pos/orders/{order}', [PosController::class, 'show'])->name('pos.orders.show');
                Route::get('/pos/orders/{order}/payment', [PaymentController::class, 'create'])->name('pos.orders.payment');
                Route::post('/pos/orders/{order}/payment', [PaymentController::class, 'store'])->name('pos.orders.payment.store');
                Route::get('/pos/split-parts/{part}/payment', [PaymentController::class, 'part'])->name('pos.split-parts.payment');
                Route::post('/pos/split-parts/{part}/payment', [PaymentController::class, 'storePart'])->name('pos.split-parts.payment.store');
                Route::post('/pos/orders/{order}/employee', [PosController::class, 'switchEmployee'])->middleware('throttle:20,1')->name('pos.orders.employee');
                Route::post('/pos/orders/{order}/employee/forget', [PosController::class, 'forgetEmployee'])->name('pos.orders.employee.forget');
                Route::post('/pos/orders/{order}/cancel', [PosController::class, 'cancel'])->name('pos.orders.cancel');
                Route::get('/pos/orders/{order}/transfer', [PosAdvancedController::class, 'transferForm'])->name('pos.orders.transfer');
                Route::post('/pos/orders/{order}/transfer', [PosAdvancedController::class, 'transfer'])->name('pos.orders.transfer.store');
                Route::post('/pos/orders/{order}/lines/{line}/void', [PosAdvancedController::class, 'void'])->name('pos.orders.lines.void');
                Route::post('/pos/orders/{order}/discount', [PosAdvancedController::class, 'discount'])->name('pos.orders.discount');
                Route::post('/pos/orders/{order}/coupon', [CouponController::class, 'applyToOrder'])->name('pos.orders.coupon');
                Route::post('/pos/orders/{order}/loyalty/redeem', [LoyaltyController::class, 'redeem'])->name('pos.orders.loyalty.redeem');
                Route::post('/pos/orders/{order}/lines/{line}/manual-price', [PosAdvancedController::class, 'manualPrice'])->name('pos.orders.lines.manual-price');
                Route::get('/pos/orders/{order}/split', [PosAdvancedController::class, 'splitForm'])->name('pos.orders.split');
                Route::post('/pos/orders/{order}/split/equal', [PosAdvancedController::class, 'splitEqual'])->name('pos.orders.split.equal');
                Route::post('/pos/orders/{order}/split/products', [PosAdvancedController::class, 'splitProducts'])->name('pos.orders.split.products');
                Route::post('/pos/splits/{plan}/cancel', [PosAdvancedController::class, 'cancelSplit'])->name('pos.splits.cancel');
                Route::get('/pos/orders/{order}/history', [PosAdvancedController::class, 'history'])->name('pos.orders.history');
                Route::get('/pos/recovery', [PosAdvancedController::class, 'recovery'])->name('pos.recovery');
                Route::post('/pos/recovery/{order}', [PosAdvancedController::class, 'recover'])->name('pos.recovery.store');
                Route::get('/pos/orders/{order}/products/{product}/configure', [PosController::class, 'configurator'])->name('pos.products.configure');
                Route::post('/pos/orders/{order}/rounds/{round}/lines', [PosController::class, 'add'])->name('pos.rounds.lines.store');
                Route::patch('/pos/orders/{order}/rounds/{round}/lines/{line}', [PosController::class, 'change'])->name('pos.rounds.lines.change');
                Route::post('/pos/orders/{order}/rounds/{round}/submit', [PosController::class, 'submit'])->name('pos.rounds.submit');

                continue;
            }

            if ($module === 'kitchen') {
                Route::get('/kitchen', [KitchenController::class, 'index'])->name('kitchen');
                Route::get('/kitchen/feed', [KitchenController::class, 'feed'])->name('kitchen.feed');
                Route::post('/kitchen/identify', [KitchenController::class, 'identify'])->middleware('throttle:20,1')->name('kitchen.identify');
                Route::patch('/kitchen/items/{item}/state', [KitchenController::class, 'transition'])->middleware('throttle:120,1')->name('kitchen.items.state');
                Route::post('/kitchen/cancellations/{cancellation}/acknowledge', [KitchenController::class, 'acknowledge'])->name('kitchen.cancellations.acknowledge');
                Route::get('/kitchen/manage', [KitchenController::class, 'manage'])->name('kitchen.manage');
                Route::post('/kitchen/stations', [KitchenController::class, 'store'])->name('kitchen.stations.store');
                Route::patch('/kitchen/stations/{station}', [KitchenController::class, 'update'])->name('kitchen.stations.update');
                Route::delete('/kitchen/stations/{station}', [KitchenController::class, 'destroy'])->name('kitchen.stations.destroy');

                continue;
            }

            if ($module === 'integrations') {
                Route::get('/integrations', [IntegrationController::class, 'center'])->name('integrations');
                Route::get('/integrations/hardware', [HardwareController::class, 'printers'])->name('hardware');
                Route::post('/integrations/hardware/printers', [HardwareController::class, 'storePrinter'])->name('hardware.printers.store');
                Route::patch('/integrations/hardware/printers/{printer}', [HardwareController::class, 'updatePrinter'])->name('hardware.printers.update');
                Route::post('/integrations/hardware/stations', [HardwareController::class, 'mapStation'])->name('hardware.stations.map');
                Route::post('/integrations/hardware/printers/{printer}/test', [HardwareController::class, 'testPrinter'])->name('hardware.printers.test');
                Route::post('/integrations/hardware/jobs/{printJob}/reprint', [HardwareController::class, 'reprint'])->name('hardware.jobs.reprint');
                Route::post('/integrations/hardware/sales/{order}/ticket', [HardwareController::class, 'printSaleTicket'])->name('hardware.sales.ticket');
                Route::post('/integrations/hardware/jobs/{printJob}/retry', [HardwareController::class, 'retry'])->name('hardware.jobs.retry');
                Route::post('/integrations/hardware/connectors', [HardwareController::class, 'createConnector'])->name('hardware.connectors.store');
                Route::get('/integrations/hardware/drawer', [HardwareController::class, 'drawerEvents'])->name('hardware.drawer');
                Route::post('/integrations/hardware/drawer/open', [HardwareController::class, 'openDrawer'])->name('hardware.drawer.open');
                Route::post('/integrations/stripe', [IntegrationController::class, 'stripeSave'])->name('integrations.stripe.save');
                Route::post('/integrations/stripe/test', [IntegrationController::class, 'stripeTest'])->name('integrations.stripe.test');
                Route::post('/integrations/stripe/disconnect', [IntegrationController::class, 'stripeDisconnect'])->name('integrations.stripe.disconnect');
                Route::post('/integrations/channel-methods', [IntegrationController::class, 'channelMethods'])->name('integrations.channels');
                Route::post('/integrations/aeat', [IntegrationController::class, 'aeatSave'])->name('integrations.aeat.save');
                Route::get('/integrations/fiscal', [FiscalController::class, 'index'])->name('fiscal');
                Route::get('/integrations/fiscal/{fiscalRecord}', [FiscalController::class, 'show'])->name('fiscal.show');
                Route::get('/integrations/fiscal/{fiscalRecord}/qr', [FiscalController::class, 'qr'])->name('fiscal.qr');
                Route::post('/integrations/fiscal/{fiscalRecord}/retry', [FiscalController::class, 'retry'])->name('fiscal.retry');
                Route::post('/integrations/fiscal/{fiscalRecord}/anular', [FiscalController::class, 'anular'])->name('fiscal.anular');
                Route::post('/integrations/accounting', [IntegrationController::class, 'accountingSave'])->name('integrations.accounting.save');
                Route::get('/integrations/exports/sales', [AccountingController::class, 'sales'])->name('exports.accounting.sales');
                Route::get('/integrations/exports/invoices', [AccountingController::class, 'invoices'])->name('exports.accounting.invoices');
                Route::get('/integrations/exports/payments', [AccountingController::class, 'payments'])->name('exports.accounting.payments');
                Route::get('/integrations/exports/cash', [AccountingController::class, 'cash'])->name('exports.accounting.cash');
                Route::get('/integrations/webhooks', [IntegrationController::class, 'webhooks'])->name('integrations.webhooks');
                Route::post('/integrations/webhooks', [IntegrationController::class, 'webhookStore'])->name('integrations.webhooks.store');
                Route::delete('/integrations/webhooks/{webhookEndpoint}', [IntegrationController::class, 'webhookDestroy'])->name('integrations.webhooks.destroy');
                Route::post('/integrations/webhooks/{webhookEndpoint}/retry', [IntegrationController::class, 'webhookRetry'])->name('integrations.webhooks.retry');
                Route::get('/integrations/coupons', [CouponController::class, 'index'])->name('coupons');
                Route::post('/integrations/coupons', [CouponController::class, 'store'])->name('coupons.store');
                Route::patch('/integrations/coupons/{coupon}', [CouponController::class, 'update'])->name('coupons.update');
                Route::get('/integrations/loyalty', [LoyaltyController::class, 'index'])->name('loyalty');
                Route::post('/integrations/loyalty', [LoyaltyController::class, 'store'])->name('loyalty.store');
                Route::patch('/integrations/loyalty/{loyaltyProgram}', [LoyaltyController::class, 'update'])->name('loyalty.update');

                continue;
            }

            if ($module === 'analytics') {
                Route::get('/analytics', [AnalyticsController::class, 'dashboard'])->name('analytics');
                Route::get('/analytics/sales', [SalesController::class, 'index'])->name('sales');
                Route::get('/analytics/sales/{order}', [SalesController::class, 'show'])->name('sales.show');
                Route::get('/analytics/sales/{order}/ticket', [SalesController::class, 'ticket'])->name('sales.ticket');
                Route::get('/analytics/invoices', [SalesController::class, 'invoices'])->name('invoices');
                Route::get('/analytics/invoices/{saleDocument}', [SalesController::class, 'invoiceShow'])->name('invoices.show');
                Route::post('/analytics/sales/{order}/invoices', [SalesController::class, 'invoiceStore'])->name('invoices.store');
                Route::get('/analytics/customers', [CustomerController::class, 'index'])->name('customers');
                Route::post('/analytics/customers', [CustomerController::class, 'store'])->name('customers.store');
                Route::get('/analytics/customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');
                Route::patch('/analytics/customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');
                Route::post('/analytics/orders/{order}/customer', [CustomerController::class, 'attach'])->name('orders.customer');
                Route::get('/analytics/stock', [StockController::class, 'index'])->name('stock');
                Route::get('/analytics/stock/{product}', [StockController::class, 'show'])->name('stock.show');
                Route::patch('/analytics/stock/{product}/settings', [StockController::class, 'settings'])->name('stock.settings');
                Route::post('/analytics/stock/{product}/entries', [StockController::class, 'entry'])->name('stock.entries');
                Route::post('/analytics/stock/{product}/adjust', [StockController::class, 'adjust'])->name('stock.adjust');
                Route::get('/analytics/audit', [AnalyticsController::class, 'audit'])->name('audit');
                Route::get('/analytics/exports/sales.csv', [AnalyticsController::class, 'exportSales'])->name('exports.sales');
                Route::get('/analytics/exports/products.csv', [AnalyticsController::class, 'exportProducts'])->name('exports.products');
                Route::get('/analytics/exports/payments.csv', [AnalyticsController::class, 'exportPayments'])->name('exports.payments');

                continue;
            }

            Route::get('/'.$module, [DashboardController::class, 'module'])
                ->defaults('module', $module)
                ->name($module);
        }
    });
