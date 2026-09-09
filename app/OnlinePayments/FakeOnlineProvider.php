<?php

namespace App\OnlinePayments;

class FakeOnlineProvider implements OnlinePaymentProvider
{
    public function name(): string
    {
        return 'fake';
    }

    public function createCheckout(int $amountMinor, string $currency, array $context): array
    {
        $id = 'pi_fake_'.bin2hex(random_bytes(12));

        return ['provider_intent_id' => $id, 'client_secret' => $id.'_secret_fake', 'checkout_url' => null, 'status' => 'processing'];
    }

    public function retrieveIntent(string $providerIntentId): array
    {
        return ['status' => 'processing', 'failure_reason' => null];
    }

    public function refund(string $providerIntentId, int $amountMinor, string $idempotencyKey): array
    {
        return ['provider_refund_id' => 're_fake_'.substr(md5($providerIntentId.$idempotencyKey), 0, 16), 'status' => 'succeeded'];
    }

    public function verifyWebhookSignature(string $payload, string $header, string $secret): bool
    {
        return hash_equals(hash_hmac('sha256', $payload, $secret), $header);
    }

    public function parseWebhookEvent(string $payload): array
    {
        $data = json_decode($payload, true) ?? [];

        return ['provider_event_id' => (string) ($data['id'] ?? ''), 'type' => (string) ($data['type'] ?? ''), 'data' => $data];
    }
}
