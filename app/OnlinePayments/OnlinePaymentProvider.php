<?php

namespace App\OnlinePayments;

interface OnlinePaymentProvider
{
    public function name(): string;

    /**
     * @return array{provider_intent_id:string,client_secret:?string,checkout_url:?string,status:string}
     */
    public function createCheckout(int $amountMinor, string $currency, array $context): array;

    /** @return array{status:string,failure_reason:?string} */
    public function retrieveIntent(string $providerIntentId): array;

    /**
     * @return array{provider_refund_id:string,status:string}
     */
    public function refund(string $providerIntentId, int $amountMinor, string $idempotencyKey): array;

    public function verifyWebhookSignature(string $payload, string $header, string $secret): bool;

    /** @return array{provider_event_id:string,type:string,data:array} */
    public function parseWebhookEvent(string $payload): array;
}
