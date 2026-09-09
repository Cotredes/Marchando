<?php

namespace App;

use App\Events\PublicOrderChanged;
use App\Models\ActiveTableOrder;
use App\Models\OnlinePaymentIntent;
use App\Models\Order;
use App\Models\PublicOrderRequest;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class PublicOrderService
{
    public function submit($restaurant, string $channel, ?int $tableId, array $cart, array $customer, string $requestKey): array
    {
        if (! $restaurant->is_active) {
            throw new InvalidArgumentException('Este restaurante no acepta pedidos ahora mismo.');
        }

        return DB::transaction(function () use ($restaurant, $channel, $tableId, $cart, $customer, $requestKey) {
            $existing = PublicOrderRequest::query()->where('restaurant_id', $restaurant->id)->where('request_key', $requestKey)->first();
            if ($existing) {
                return [$existing, $existing->public_token];
            }
            $availability = app(RestaurantAvailability::class);
            $now = CarbonImmutable::now();
            $scheduled = ($customer['fulfillment_mode'] ?? 'asap') === 'scheduled' && ! empty($customer['requested_at']);
            $channelOpen = $availability->isChannelOpenAt($restaurant, $channel, $now);
            if (! in_array($channel, ['dine_in', 'takeaway', 'delivery'], true) || ($channel === 'dine_in' && (! $restaurant->dine_in_enabled || ! $restaurant->qr_ordering_enabled)) || (! $channelOpen && ! $scheduled)) {
                throw new InvalidArgumentException('Este canal no acepta pedidos ahora mismo.');
            }
            if ($scheduled) {
                $requested = CarbonImmutable::parse($customer['requested_at']);
                if ($requested->lessThanOrEqualTo($now) || ! $availability->isChannelOpenAt($restaurant, $channel, $requested)) {
                    throw new InvalidArgumentException('La hora elegida no está disponible.');
                }
            }
            if ($channel === 'dine_in' && ! $tableId) {
                throw new InvalidArgumentException('No se ha identificado la mesa.');
            }
            $subtotal = 0;
            $quoted = [];
            foreach ($cart as $line) {
                $product = $restaurant->products()->with(['category', 'formats', 'modifierGroupAssignments.group.options'])->findOrFail((int) $line['product_id']);
                $quote = app(PublicCatalogService::class)->quote($product, $channel, $line['format_id'] ?? null, $line['selections'] ?? [], (int) $line['quantity'], $line['notes'] ?? null);
                $subtotal += $quote['price']['total_minor'] * $quote['quantity'];
                $quoted[] = $quote;
            }
            if ($subtotal < 1) {
                throw new InvalidArgumentException('El carrito está vacío.');
            }
            $fee = $channel === 'delivery' ? (int) round(((float) ($restaurant->delivery_fee ?? 0)) * 100) : 0;
            $minimum = $channel === 'delivery' ? (int) round(((float) ($restaurant->delivery_minimum_order ?? 0)) * 100) : 0;
            if ($channel === 'delivery' && $subtotal < $minimum) {
                throw new InvalidArgumentException('Te faltan '.number_format(($minimum - $subtotal) / 100, 2, ',', '.').' € para alcanzar el pedido mínimo.');
            }
            $distance = null;
            if ($channel === 'delivery' && ($customer['latitude'] ?? null) !== null && ($customer['longitude'] ?? null) !== null && $restaurant->latitude !== null && $restaurant->longitude !== null) {
                $distance = app(DeliveryDistance::class)->meters((float) $restaurant->latitude, (float) $restaurant->longitude, (float) $customer['latitude'], (float) $customer['longitude']);
                if ($restaurant->delivery_radius_km !== null && $distance > (float) $restaurant->delivery_radius_km * 1000) {
                    throw new InvalidArgumentException('Esta dirección está fuera de nuestra zona de reparto.');
                }
            }
            $coupon = null;
            $couponDiscount = 0;
            if (filled($customer['coupon_code'] ?? null) && $channel !== 'dine_in') {
                $coupon = app(CouponService::class)->validate($restaurant, $customer['coupon_code'], $channel, $subtotal, app(CouponService::class)->customerFp(null, $customer['phone'] ?? null));
                $couponDiscount = app(CouponService::class)->amountFor($coupon, $subtotal);
            }
            $token = Str::random(64);
            $request = PublicOrderRequest::create(['restaurant_id' => $restaurant->id, 'dining_table_id' => $tableId, 'channel' => $channel, 'status' => 'pending', 'public_token_hash' => hash('sha256', $token), 'public_token' => $token, 'request_key' => $requestKey, 'payload_hash' => hash('sha256', json_encode([$channel, $tableId, $cart, $customer], JSON_THROW_ON_ERROR)), 'currency' => $restaurant->currency, 'subtotal_minor' => $subtotal, 'delivery_fee_minor' => $fee, 'total_minor' => $subtotal - $couponDiscount + $fee, 'coupon_id' => $coupon?->id, 'coupon_code' => $coupon?->code, 'customer_name' => $customer['name'] ?? null, 'customer_phone' => $customer['phone'] ?? null, 'customer_email' => $customer['email'] ?? null, 'delivery_address' => $customer['address'] ?? null, 'delivery_latitude' => $customer['latitude'] ?? null, 'delivery_longitude' => $customer['longitude'] ?? null, 'distance_meters' => $distance, 'fulfillment_mode' => $customer['fulfillment_mode'] ?? 'asap', 'requested_at' => $customer['requested_at'] ?? $now]);
            foreach ($quoted as $quote) {
                $request->lines()->create(['product_id' => $quote['product']->id, 'product_format_id' => $quote['format']?->id, 'product_name' => $quote['price']['product_name'], 'format_name' => $quote['price']['format_name'], 'quantity' => $quote['quantity'], 'unit_total_minor' => $quote['price']['total_minor'], 'line_total_minor' => $quote['price']['total_minor'] * $quote['quantity'], 'vat_rate' => $quote['price']['vat_rate'], 'selections' => $quote['selections'], 'snapshot' => $quote['snapshot'], 'notes' => $quote['notes']]);
            }
            if ($this->automatic($restaurant, $channel)) {
                $this->acceptLocked($request, $restaurant);
            }

            return [$request->fresh(), $token];
        });
    }

    public function accept(PublicOrderRequest $incoming, $restaurant): PublicOrderRequest
    {
        return DB::transaction(fn () => $this->acceptLocked(PublicOrderRequest::query()->lockForUpdate()->with('lines')->findOrFail($incoming->id), $restaurant));
    }

    public function reject(PublicOrderRequest $incoming, string $reason, $restaurant): PublicOrderRequest
    {
        return DB::transaction(function () use ($incoming, $reason, $restaurant) {
            $request = PublicOrderRequest::query()->lockForUpdate()->findOrFail($incoming->id);
            abort_unless($request->restaurant_id === $restaurant->id, 404);
            if ($request->status !== 'pending') {
                throw new InvalidArgumentException('La solicitud ya no está pendiente.');
            } $request->update(['status' => 'rejected', 'rejection_reason' => mb_substr($reason, 0, 500), 'rejected_at' => now(), 'version' => $request->version + 1]);
            PublicOrderChanged::dispatch($request->fresh());

            return $request;
        });
    }

    private function acceptLocked(PublicOrderRequest $request, $restaurant): PublicOrderRequest
    {
        if ($request->status === 'accepted') {
            return $request;
        }
        if ($request->status !== 'pending') {
            throw new InvalidArgumentException('La solicitud ya no está pendiente.');
        }
        $table = $request->dining_table_id ? $restaurant->diningTables()->lockForUpdate()->findOrFail($request->dining_table_id) : null;
        $order = null;
        if ($table) {
            $assignment = ActiveTableOrder::query()->where('restaurant_id', $restaurant->id)->where('dining_table_id', $table->id)->lockForUpdate()->first();
            if ($assignment) {
                $order = Order::query()->lockForUpdate()->findOrFail($assignment->order_id);
            }
        }
        if (! $order) {
            $order = $restaurant->orders()->create(['dining_table_id' => $table?->id, 'channel' => $request->channel, 'origin' => $request->channel === 'dine_in' ? 'qr' : 'public_'.$request->channel, 'status' => 'open', 'payment_status' => 'unpaid', 'currency' => $request->currency, 'total_minor' => 0, 'business_date' => now($restaurant->timezone)->toDateString(), 'opened_at' => now()]);
            if ($table) {
                ActiveTableOrder::create(['restaurant_id' => $restaurant->id, 'dining_table_id' => $table->id, 'order_id' => $order->id]);
            }
        }
        if ($order->status !== 'open') {
            throw new InvalidArgumentException('La cuenta de la mesa ya no está disponible.');
        }
        if ($order->payments()->where('status', 'succeeded')->exists()) {
            throw new InvalidArgumentException('La cuenta ya ha iniciado el cobro.');
        }
        $round = $order->rounds()->create(['restaurant_id' => $restaurant->id, 'sequence' => ((int) $order->rounds()->max('sequence')) + 1, 'status' => 'submitted', 'created_by_user_id' => null, 'created_by_employee_id' => null, 'submitted_by_user_id' => null, 'submitted_by_employee_id' => null, 'submitted_at' => now(), 'submission_key' => 'public-request-'.$request->id]);
        $costs = $restaurant->products()->whereIn('id', $request->lines->pluck('product_id')->filter()->unique())->pluck('cost_minor', 'id');
        foreach ($request->lines as $position => $line) {
            $roundLine = $round->lines()->create(['restaurant_id' => $restaurant->id, 'order_id' => $order->id, 'product_id' => $line->product_id, 'product_format_id' => $line->product_format_id, 'product_name' => $line->product_name, 'format_name' => $line->format_name, 'quantity' => $line->quantity, 'voided_quantity' => 0, 'unit_base_minor' => $line->snapshot['unit_base_minor'] ?? $line->unit_total_minor, 'unit_modifiers_minor' => $line->snapshot['unit_modifiers_minor'] ?? 0, 'unit_total_minor' => $line->unit_total_minor, 'cost_minor' => $line->product_id ? ($costs[$line->product_id] ?? null) : null, 'line_total_minor' => $line->line_total_minor, 'active_line_total_minor' => $line->line_total_minor, 'vat_rate' => $line->vat_rate, 'currency' => $request->currency, 'notes' => $line->notes, 'snapshot' => $line->snapshot, 'position' => ($position + 1) * 10]);
            foreach ($line->snapshot['modifiers'] ?? [] as $modifier) {
                $roundLine->modifiers()->create(['restaurant_id' => $restaurant->id, ...$modifier]);
            }
        }
        $order->update(['total_minor' => $order->lines()->sum('active_line_total_minor') + $request->delivery_fee_minor, 'version' => $order->version + 1]);
        if ($request->delivery_fee_minor > 0) {
            $order->charges()->create(['restaurant_id' => $restaurant->id, 'type' => 'delivery', 'label' => 'Reparto', 'amount_minor' => $request->delivery_fee_minor, 'currency' => $request->currency]);
        }
        if ($request->channel !== 'dine_in') {
            $prep = $request->channel === 'takeaway' ? (int) ($restaurant->takeaway_prep_minutes ?? 20) : (int) ($restaurant->delivery_prep_minutes ?? 40);
            $order->fulfillment()->create(['restaurant_id' => $restaurant->id, 'channel' => $request->channel, 'status' => 'accepted', 'fulfillment_mode' => $request->fulfillment_mode, 'customer_name' => $request->customer_name, 'customer_phone' => $request->customer_phone, 'customer_email' => $request->customer_email, 'delivery_address' => $request->delivery_address, 'delivery_latitude' => $request->delivery_latitude, 'delivery_longitude' => $request->delivery_longitude, 'distance_meters' => $request->distance_meters, 'delivery_fee_minor' => $request->delivery_fee_minor, 'requested_at' => $request->requested_at, 'estimated_ready_at' => now()->addMinutes($prep)]);
        }
        app(KitchenService::class)->dispatchRound($round);
        app(StockService::class)->consumeRound($round->fresh('lines.product'));
        if ($request->coupon_id) {
            app(CouponService::class)->applyToOrder($order, $request->coupon_code, app(CouponService::class)->customerFp($order->customer_id, $request->customer_phone), null);
            $order = $order->fresh();
        }
        $intent = OnlinePaymentIntent::query()->where('public_order_request_id', $request->id)->where('status', 'succeeded')->first();
        if ($intent && ! $intent->order_id) {
            app(OnlinePaymentService::class)->linkIntentToOrder($intent, $order->fresh(), null);
        }
        if ($request->channel !== 'dine_in' && ! $order->customer_id) {
            $customer = app(CustomerService::class)->findOrCreateForSale($restaurant, ['name' => $request->customer_name, 'phone' => $request->customer_phone, 'email' => $request->customer_email]);
            if ($customer) {
                $order->update(['customer_id' => $customer->id]);
            }
        }
        $request->update(['status' => 'accepted', 'accepted_order_id' => $order->id, 'accepted_round_id' => $round->id, 'accepted_at' => now(), 'version' => $request->version + 1]);
        PublicOrderChanged::dispatch($request->fresh());
        app(OutboundWebhookService::class)->fire($restaurant, 'order.accepted', ['request_id' => $request->id, 'order_id' => $order->id, 'channel' => $request->channel, 'total_minor' => $order->fresh()->total_minor]);

        return $request->fresh(['order', 'round']);
    }

    private function automatic($restaurant, string $channel): bool
    {
        return $restaurant->{$channel === 'dine_in' ? 'qr_acceptance_mode' : $channel.'_acceptance_mode'} === 'automatic';
    }
}
