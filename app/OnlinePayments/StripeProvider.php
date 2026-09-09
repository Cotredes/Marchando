<?php

namespace App\OnlinePayments;

use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

class StripeProvider implements OnlinePaymentProvider
{
    public function __construct(private readonly string $secretKey, private readonly bool $testMode = true) {}

    public function name(): string
    {
        return 'stripe';
    }

    public function createCheckout(int $amountMinor, string $currency, array $context): array
    {
        if ($amountMinor < 1) {
            throw new InvalidArgumentException('Importe no válido para Stripe.');
        }
        $response = Http::withBasicAuth($this->secretKey, '')->timeout(15)->asForm()->post('https://api.stripe.com/v1/checkout/sessions', [
            'mode' => 'payment',
            'success_url' => $context['success_url'],
            'cancel_url' => $context['cancel_url'],
            'line_items[0][price_data][currency]' => strtolower($currency),
            'line_items[0][price_data][product_data][name]' => mb_substr($context['description'] ?? 'Pedido', 0, 200),
            'line_items[0][price_data][unit_amount]' => $amountMinor,
            'line_items[0][quantity]' => 1,
            'metadata[request_key]' => $context['request_key'] ?? '',
            'payment_intent_data[metadata][request_key]' => $context['request_key'] ?? '',
        ]);
        if (! $response->successful()) {
            throw new InvalidArgumentException('Stripe no ha podido crear la sesión de pago. Revisa las credenciales.');
        }
        $data = $response->json();

        return ['provider_intent_id' => (string) ($data['payment_intent'] ?? $data['id']), 'client_secret' => null, 'checkout_url' => $data['url'] ?? null, 'status' => 'processing'];
    }

    public function retrieveIntent(string $providerIntentId): array
    {
        $id = $providerIntentId;
        if (str_starts_with($id, 'cs_')) {
            $session = Http::withBasicAuth($this->secretKey, '')->timeout(15)->get('https://api.stripe.com/v1/checkout/sessions/'.urlencode($id))->json();
            $id = $session['payment_intent'] ?? $id;
        }
        $intent = Http::withBasicAuth($this->secretKey, '')->timeout(15)->get('https://api.stripe.com/v1/payment_intents/'.urlencode((string) $id))->json();
        $status = (string) ($intent['status'] ?? 'unknown');

        return ['status' => $status, 'failure_reason' => $intent['last_payment_error']['message'] ?? null];
    }

    public function refund(string $providerIntentId, int $amountMinor, string $idempotencyKey): array
    {
        $response = Http::withBasicAuth($this->secretKey, '')->withHeaders(['Idempotency-Key' => $idempotencyKey])->timeout(20)->asForm()->post('https://api.stripe.com/v1/refunds', [
            'payment_intent' => $providerIntentId, 'amount' => $amountMinor,
        ]);
        if (! $response->successful()) {
            $message = $response->json('error.message') ?? 'Stripe rechazó el reembolso.';

            throw new InvalidArgumentException($message);
        }
        $data = $response->json();

        return ['provider_refund_id' => (string) $data['id'], 'status' => ($data['status'] ?? '') === 'succeeded' ? 'succeeded' : 'requested'];
    }

    public function verifyWebhookSignature(string $payload, string $header, string $secret): bool
    {
        $timestamp = null;
        $signatures = [];
        foreach (explode(',', $header) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, null);
            if ($key === 't') {
                $timestamp = (int) $value;
            }
            if ($key === 'v1' && $value) {
                $signatures[] = $value;
            }
        }
        if (! $timestamp || $signatures === [] || abs(time() - $timestamp) > 300) {
            return false;
        }
        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);
        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                return true;
            }
        }

        return false;
    }

    public function parseWebhookEvent(string $payload): array
    {
        $data = json_decode($payload, true) ?? [];

        return ['provider_event_id' => (string) ($data['id'] ?? ''), 'type' => (string) ($data['type'] ?? ''), 'data' => $data];
    }
}
