<?php

namespace App\Http\Controllers;

use App\Models\PublicOrderRequest;
use App\Models\Restaurant;
use App\OnlinePaymentService;
use App\PublicCatalogService;
use App\PublicOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class PublicOrderingController extends Controller
{
    public function qr(string $token): View
    {
        $table = app(PublicCatalogService::class)->table($token);
        abort_unless($table->restaurant->public_menu_enabled, 404);

        return $this->renderCatalog($table->restaurant, 'dine_in', $this->key('qr', $token), $table);
    }

    public function qrProduct(string $token, int $product): View
    {
        $table = app(PublicCatalogService::class)->table($token);

        return view('public.product', ['restaurant' => $table->restaurant, 'channel' => 'dine_in', 'product' => app(PublicCatalogService::class)->product($table->restaurant, $product), 'context' => $this->key('qr', $token), 'backUrl' => route('tables.resolve', $token)]);
    }

    public function qrAdd(Request $request, string $token): RedirectResponse
    {
        $table = app(PublicCatalogService::class)->table($token);

        return $this->addToCart($request, $table->restaurant, 'dine_in', $this->key('qr', $token), route('tables.resolve', $token));
    }

    public function qrCart(string $token): View
    {
        $table = app(PublicCatalogService::class)->table($token);

        return $this->cartFor($table->restaurant, 'dine_in', $this->key('qr', $token), route('tables.resolve', $token), $table);
    }

    public function qrCheckout(string $token): View
    {
        $table = app(PublicCatalogService::class)->table($token);

        return view('public.checkout', ['restaurant' => $table->restaurant, 'channel' => 'dine_in', 'cart' => session()->get($this->key('qr', $token), []), 'table' => $table, 'submitUrl' => route('public.qr.submit', $token)]);
    }

    public function qrSubmit(Request $request, string $token): RedirectResponse
    {
        $table = app(PublicCatalogService::class)->table($token);

        return $this->submitCart($request, $table->restaurant, 'dine_in', $this->key('qr', $token), $table->id, route('public.qr.cart', $token));
    }

    public function catalog(Restaurant $restaurant, string $channel): View
    {
        abort_unless($restaurant->is_active, 404);
        abort_unless(in_array($channel, ['takeaway', 'delivery'], true) && $restaurant->public_menu_enabled, 404);

        return $this->renderCatalog($restaurant, $channel, $this->key('restaurant', $restaurant->id.'-'.$channel));
    }

    public function product(Request $request, Restaurant $restaurant, string $channel, int $product): View
    {
        abort_unless($restaurant->is_active, 404);
        abort_unless($restaurant->public_menu_enabled, 404);
        $model = app(PublicCatalogService::class)->product($restaurant, $product);

        return view('public.product', ['restaurant' => $restaurant, 'channel' => $channel, 'product' => $model, 'context' => $this->key('restaurant', $restaurant->id.'-'.$channel)]);
    }

    public function add(Request $request, Restaurant $restaurant, string $channel): RedirectResponse
    {
        abort_unless($restaurant->is_active, 404);

        return $this->addToCart($request, $restaurant, $channel, $this->key('restaurant', $restaurant->id.'-'.$channel), route('public.catalog', [$restaurant, $channel]));
    }

    public function cart(Restaurant $restaurant, string $channel): View
    {
        abort_unless($restaurant->is_active, 404);

        return $this->cartFor($restaurant, $channel, $this->key('restaurant', $restaurant->id.'-'.$channel));
    }

    private function cartFor(Restaurant $restaurant, string $channel, string $key, ?string $backUrl = null, $table = null): View
    {
        $cart = collect(session()->get($key, []));
        $lines = $cart->map(function ($line) use ($restaurant, $channel) {
            try {
                return app(PublicCatalogService::class)->quote(app(PublicCatalogService::class)->product($restaurant, $line['product_id']), $channel, $line['format_id'] ?? null, $line['selections'] ?? [], (int) $line['quantity'], $line['notes'] ?? null);
            } catch (InvalidArgumentException) {
                return null;
            }
        })->filter()->values();

        return view('public.cart', compact('restaurant', 'channel', 'lines', 'backUrl', 'table'));
    }

    public function checkout(Restaurant $restaurant, string $channel): View
    {
        abort_unless($restaurant->is_active, 404);

        return view('public.checkout', ['restaurant' => $restaurant, 'channel' => $channel, 'cart' => session()->get($this->key('restaurant', $restaurant->id.'-'.$channel), [])]);
    }

    public function submit(Request $request, Restaurant $restaurant, string $channel): RedirectResponse
    {
        abort_unless($restaurant->is_active, 404);

        return $this->submitCart($request, $restaurant, $channel, $this->key('restaurant', $restaurant->id.'-'.$channel));
    }

    private function submitCart(Request $request, Restaurant $restaurant, string $channel, string $cartKey, ?int $tableId = null, ?string $backUrl = null): RedirectResponse
    {
        $data = $request->validate(['request_key' => ['required', 'string', 'max:120'], 'name' => ['required', 'string', 'max:120'], 'phone' => ['required', 'string', 'max:40'], 'email' => ['nullable', 'email', 'max:160'], 'address' => [$channel === 'delivery' ? 'required' : 'nullable', 'string', 'max:500'], 'latitude' => ['nullable', 'numeric', 'between:-90,90'], 'longitude' => ['nullable', 'numeric', 'between:-180,180'], 'fulfillment_mode' => ['required', 'in:asap,scheduled'], 'requested_at' => ['nullable', 'date'], 'coupon_code' => ['nullable', 'string', 'max:40']]);
        try {
            [$publicRequest, $token] = app(PublicOrderService::class)->submit($restaurant, $channel, $tableId, session()->get($cartKey, []), $data, $data['request_key']);
            session()->forget($cartKey);
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['order' => $exception->getMessage()])->withInput();
        }

        return redirect()->route('public.order.track', ['token' => $token]);
    }

    private function addToCart(Request $request, Restaurant $restaurant, string $channel, string $key, ?string $redirect = null): RedirectResponse
    {
        $data = $request->validate(['product_id' => ['required', 'integer'], 'format_id' => ['nullable', 'integer'], 'quantity' => ['required', 'integer', 'min:1', 'max:20'], 'selections' => ['array'], 'notes' => ['nullable', 'string', 'max:500']]);
        try {
            $quote = app(PublicCatalogService::class)->quote(app(PublicCatalogService::class)->product($restaurant, $data['product_id']), $channel, $data['format_id'] ?? null, $data['selections'] ?? [], (int) $data['quantity'], $data['notes'] ?? null);
            $cart = session()->get($key, []);
            $cart[] = ['product_id' => $data['product_id'], 'format_id' => $quote['format']?->id, 'selections' => $quote['selections'], 'quantity' => $quote['quantity'], 'notes' => $quote['notes']];
            session()->put($key, $cart);
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['product' => $exception->getMessage()])->withInput();
        }

        return redirect($redirect ?: url()->previous())->with('status', 'Añadido al carrito.');
    }

    public function track(string $token): View
    {
        $request = PublicOrderRequest::query()->where('public_token_hash', hash('sha256', $token))->with(['restaurant', 'lines', 'order'])->firstOrFail();
        $intent = app(OnlinePaymentService::class)->intentForRequest($request);
        $onlineAllowed = in_array($request->channel, ['takeaway', 'delivery'], true) && $request->status === 'pending' && app(OnlinePaymentService::class)->onlineAllowed($request->restaurant, $request->channel);

        return view('public.track', compact('request', 'intent', 'onlineAllowed'));
    }

    public function snapshot(string $token)
    {
        $request = PublicOrderRequest::query()->where('public_token_hash', hash('sha256', $token))->with(['lines', 'order.fulfillment'])->firstOrFail();
        $intent = app(OnlinePaymentService::class)->intentForRequest($request);

        return response()->json(['status' => $request->status, 'channel' => $request->channel, 'total_minor' => $request->total_minor, 'reason' => $request->rejection_reason, 'fulfillment' => $request->order?->fulfillment?->status, 'paid_online' => $intent?->status === 'succeeded', 'online_status' => $intent?->status, 'server_time' => now()->toISOString()])->header('Cache-Control', 'private, no-cache')->header('Referrer-Policy', 'no-referrer');
    }

    private function renderCatalog($restaurant, string $channel, string $context, $table = null): View
    {
        $products = app(PublicCatalogService::class)->products($restaurant, $channel)->groupBy('category_id');
        $categories = $restaurant->categories()->where('is_active', true)->where('available_'.$channel, true)->orderBy('position')->get();

        return view('public.catalog', compact('restaurant', 'channel', 'products', 'categories', 'context', 'table'));
    }

    private function key(string $type, string $value): string
    {
        return 'public_cart_'.sha1($type.'-'.$value);
    }
}
