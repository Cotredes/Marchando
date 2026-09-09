<?php

namespace App;

use App\Models\ChannelPaymentMethod;
use App\Models\Employee;
use App\Models\Integration;
use App\Models\OnlinePaymentIntent;
use App\Models\OnlineRefund;
use App\Models\ProviderWebhookEvent;
use App\Models\PublicOrderRequest;
use App\Models\User;
use App\OnlinePayments\FakeOnlineProvider;
use App\OnlinePayments\OnlinePaymentProvider;
use App\OnlinePayments\StripeProvider;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class OnlinePaymentService
{
    public function integration($restaurant): Integration
    {
        return Integration::query()->firstOrCreate(
            ['restaurant_id' => $restaurant->id, 'provider' => 'stripe'],
            ['status' => 'not_configured', 'mode' => 'test']
        );
    }

    public function provider($restaurant): OnlinePaymentProvider
    {
        $integration = $this->integration($restaurant);
        $settings = $integration->settings ?? [];
        if (($settings['driver'] ?? 'stripe') === 'fake' || ! filled($integration->secret('secret_key'))) {
            return new FakeOnlineProvider;
        }

        return new StripeProvider($integration->secret('secret_key'), ($integration->mode ?? 'test') === 'test');
    }

    public function isLive(OnlinePaymentProvider $provider): bool
    {
        return $provider instanceof StripeProvider;
    }

    public function isConnected($restaurant): bool
    {
        $integration = $this->integration($restaurant);

        return in_array($integration->status, ['connected', 'test'], true);
    }

    public function testConnection($restaurant): void
    {
        $integration = $this->integration($restaurant);
        $provider = $this->provider($restaurant);
        if ($provider instanceof FakeOnlineProvider) {
            $integration->update(['status' => 'test', 'last_check_at' => now(), 'last_error' => null]);

            return;
        }
        try {
            $provider->retrieveIntent('pi_unchecked_probe');
            $integration->update(['status' => 'connected', 'last_check_at' => now(), 'last_error' => null]);
        } catch (InvalidArgumentException $exception) {
            $message = $exception->getMessage();
            if (str_contains($message, 'Revisa las credenciales') || str_contains($message, 'autentic')) {
                $integration->update(['status' => 'error', 'last_check_at' => now(), 'last_error' => 'Stripe no ha podido autenticarse. Revisa las credenciales.']);
            } else {
                $integration->update(['status' => 'connected', 'last_check_at' => now(), 'last_error' => null]);
            }
        }
    }

    public function channelMethods($restaurant, string $channel): array
    {
        $rows = $restaurant->channelPaymentMethods()->where('channel', $channel)->get()->keyBy('method');
        $defaults = ['cash' => true, 'card' => true, 'online' => in_array($channel, ['takeaway', 'delivery'], true)];

        return collect(ChannelPaymentMethod::METHODS)->mapWithKeys(fn ($method) => [
            $method => $rows->has($method) ? (bool) $rows->get($method)->enabled : ($defaults[$method] ?? false),
        ])->all();
    }

    public function onlineAllowed($restaurant, string $channel): bool
    {
        return (bool) ($this->channelMethods($restaurant, $channel)['online'] ?? false) && $this->isConnected($restaurant);
    }

    public function createIntent($restaurant, PublicOrderRequest $request): OnlinePaymentIntent
    {
        abort_unless($request->restaurant_id === $restaurant->id, 404);
        if (! in_array($request->channel, ['takeaway', 'delivery'], true)) {
            throw new InvalidArgumentException('El pago online solo está disponible para Take Away y Delivery.');
        }
        if (! $this->onlineAllowed($restaurant, $request->channel)) {
            throw new InvalidArgumentException('El pago online no está disponible para este canal.');
        }
        if ($request->status !== 'pending') {
            throw new InvalidArgumentException('La solicitud ya no admite pagos.');
        }

        return DB::transaction(function () use ($restaurant, $request): OnlinePaymentIntent {
            $existing = OnlinePaymentIntent::query()->where('restaurant_id', $restaurant->id)->where('request_key', 'online-'.$request->request_key)->first();
            if ($existing) {
                return $existing;
            }
            $integration = $this->integration($restaurant);
            $provider = $this->provider($restaurant);
            $result = $provider->createCheckout($request->total_minor, $request->currency, [
                'description' => $restaurant->name.' · Pedido '.($request->channel === 'delivery' ? 'delivery' : 'take away'),
                'request_key' => $request->request_key,
                'success_url' => route('public.order.online.return', ['token' => $request->public_token]),
                'cancel_url' => route('public.order.track', ['token' => $request->public_token]),
            ]);

            return OnlinePaymentIntent::create([
                'restaurant_id' => $restaurant->id, 'public_order_request_id' => $request->id, 'channel' => $request->channel,
                'provider' => $provider->name(), 'mode' => $provider instanceof FakeOnlineProvider ? 'test' : ($integration->mode ?? 'test'),
                'amount_minor' => $request->total_minor, 'currency' => $request->currency,
                'provider_intent_id' => $result['provider_intent_id'], 'client_secret' => $result['client_secret'],
                'checkout_url' => $result['checkout_url'], 'status' => $result['status'], 'request_key' => 'online-'.$request->request_key,
            ]);
        });
    }

    public function intentForRequest(PublicOrderRequest $request): ?OnlinePaymentIntent
    {
        return OnlinePaymentIntent::query()->where('public_order_request_id', $request->id)->latest('id')->first();
    }

    public function handleWebhook($restaurant, string $providerName, string $payload, string $signature): ProviderWebhookEvent
    {
        $provider = $this->provider($restaurant);
        $integration = $this->integration($restaurant);
        $secret = $provider instanceof FakeOnlineProvider ? 'fake-secret' : ($integration->secret('webhook_secret') ?? '');
        if (! $provider->verifyWebhookSignature($payload, $signature, $secret)) {
            throw new InvalidArgumentException('Firma del webhook no válida.');
        }
        $parsed = $provider->parseWebhookEvent($payload);
        if (! filled($parsed['provider_event_id'] ?? null)) {
            throw new InvalidArgumentException('Evento sin identificador.');
        }

        return DB::transaction(function () use ($restaurant, $providerName, $parsed, $payload): ProviderWebhookEvent {
            $existing = ProviderWebhookEvent::query()->where('provider_event_id', $parsed['provider_event_id'])->first();
            if ($existing) {
                return $existing;
            }
            $event = ProviderWebhookEvent::create(['restaurant_id' => $restaurant->id, 'provider' => $providerName, 'provider_event_id' => $parsed['provider_event_id'], 'type' => $parsed['type'], 'payload' => json_decode($payload, true) ?? [], 'status' => 'received']);
            $this->applyWebhookEvent($event);

            return $event->fresh();
        });
    }

    public function applyWebhookEvent(ProviderWebhookEvent $event): void
    {
        DB::transaction(function () use ($event): void {
            $event = ProviderWebhookEvent::query()->lockForUpdate()->findOrFail($event->id);
            if ($event->status === 'processed') {
                return;
            }
            $payload = $event->payload ?? [];
            $intent = $this->locateIntent($event->restaurant, $payload);
            if ($intent) {
                $intent = OnlinePaymentIntent::query()->lockForUpdate()->findOrFail($intent->id);
                match ($event->type) {
                    'checkout.session.completed', 'payment_intent.succeeded' => $this->markIntentSucceeded($intent),
                    'payment_intent.payment_failed', 'checkout.session.expired' => $intent->update(['status' => 'failed', 'failure_reason' => mb_substr((string) ($payload['failure_reason'] ?? 'El proveedor rechazó el pago.'), 0, 500)]),
                    default => null,
                };
            }
            $event->update(['status' => 'processed', 'processed_at' => now()]);
        });
    }

    private function locateIntent($restaurant, array $payload): ?OnlinePaymentIntent
    {
        $object = $payload['data']['object'] ?? $payload['object'] ?? [];
        foreach (['payment_intent', 'paymentIntent', 'id'] as $key) {
            if (filled($object[$key] ?? null)) {
                $found = OnlinePaymentIntent::query()->where('restaurant_id', $restaurant->id)->where('provider_intent_id', $object[$key])->first();
                if ($found) {
                    return $found;
                }
            }
        }
        $requestKey = $object['metadata']['request_key'] ?? null;
        if (filled($requestKey)) {
            return OnlinePaymentIntent::query()->where('restaurant_id', $restaurant->id)->where('request_key', 'online-'.$requestKey)->first();
        }

        return null;
    }

    public function markIntentSucceeded(OnlinePaymentIntent $intent): OnlinePaymentIntent
    {
        return DB::transaction(function () use ($intent): OnlinePaymentIntent {
            $intent = OnlinePaymentIntent::query()->lockForUpdate()->findOrFail($intent->id);
            if (in_array($intent->status, ['succeeded', 'refunded'], true)) {
                return $intent;
            }
            if (in_array($intent->status, ['failed'], true)) {
                throw new InvalidArgumentException('La intención de pago ya figura como fallida.');
            }
            $intent->update(['status' => 'succeeded', 'failure_reason' => null]);

            return $intent->fresh();
        });
    }

    public function linkIntentToOrder(OnlinePaymentIntent $intent, $order, ?User $actor): void
    {
        DB::transaction(function () use ($intent, $order, $actor): void {
            $intent = OnlinePaymentIntent::query()->lockForUpdate()->findOrFail($intent->id);
            abort_unless($intent->status === 'succeeded' && ! $intent->order_id, 422, 'La intención de pago no se puede vincular.');
            abort_unless($intent->restaurant_id === $order->restaurant_id, 404);
            $intent->update(['order_id' => $order->id]);
            app(FinancialService::class)->payOnline($order, $intent, $actor);
        });
    }

    public function refund(OnlinePaymentIntent $incoming, int $amountMinor, string $reason, User $user, ?Employee $employee, string $requestKey): OnlineRefund
    {
        return DB::transaction(function () use ($incoming, $amountMinor, $reason, $user, $employee, $requestKey): OnlineRefund {
            $intent = OnlinePaymentIntent::query()->lockForUpdate()->findOrFail($incoming->id);
            if (! in_array($intent->status, ['succeeded', 'partially_refunded'], true)) {
                throw new InvalidArgumentException('Solo se puede reembolsar un pago completado.');
            }
            $remaining = $intent->amount_minor - $intent->refundedMinor();
            if ($amountMinor < 1 || $amountMinor > $remaining) {
                throw new InvalidArgumentException('El importe a reembolsar no es válido.');
            }
            $existing = OnlineRefund::query()->where('restaurant_id', $intent->restaurant_id)->where('request_key', $requestKey)->first();
            if ($existing) {
                return $existing;
            }
            $refund = OnlineRefund::create(['restaurant_id' => $intent->restaurant_id, 'online_payment_intent_id' => $intent->id, 'user_id' => $user->id, 'employee_id' => $employee?->id, 'amount_minor' => $amountMinor, 'reason' => mb_substr($reason, 0, 500), 'status' => 'requested', 'request_key' => $requestKey]);
            try {
                $result = $this->provider($intent->restaurant)->refund($intent->provider_intent_id, $amountMinor, $requestKey);
                $refund->update(['status' => $result['status'], 'provider_refund_id' => $result['provider_refund_id']]);
            } catch (InvalidArgumentException $exception) {
                $refund->update(['status' => 'failed', 'failure_reason' => mb_substr($exception->getMessage(), 0, 500)]);

                throw new InvalidArgumentException('El reembolso falló en el proveedor: '.$exception->getMessage());
            }
            $left = $intent->amount_minor - $intent->fresh()->refundedMinor();
            $intent->update(['status' => $left <= 0 ? 'refunded' : 'partially_refunded']);

            return $refund->fresh();
        });
    }

    public function ensureOnlineMethod($restaurant)
    {
        return $restaurant->paymentMethods()->firstOrCreate(['code' => 'online'], ['name' => 'Online', 'is_cash' => false, 'is_active' => true, 'position' => 30]);
    }
}
