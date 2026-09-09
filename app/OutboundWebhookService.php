<?php

namespace App;

use App\Models\OutboundWebhookDelivery;
use Illuminate\Support\Facades\Http;

class OutboundWebhookService
{
    public function fire($restaurant, string $event, array $payload): void
    {
        try {
            $payload['event'] = $event;
            $payload['restaurant_id'] = $restaurant->id;
            $payload['sent_at'] = now()->toISOString();
            foreach ($restaurant->webhookEndpoints()->where('is_active', true)->get() as $endpoint) {
                if (! $endpoint->listens($event)) {
                    continue;
                }
                $delivery = OutboundWebhookDelivery::create(['outbound_webhook_endpoint_id' => $endpoint->id, 'event' => $event, 'payload' => $payload, 'status' => 'pending', 'attempts' => 0]);
                $this->attempt($delivery->fresh());
            }
        } catch (\Throwable) {
        }
    }

    public function attempt(OutboundWebhookDelivery $delivery): OutboundWebhookDelivery
    {
        $delivery->loadMissing('endpoint');
        $endpoint = $delivery->endpoint;
        if (! $endpoint || ! $endpoint->is_active) {
            $delivery->update(['status' => 'error']);

            return $delivery->fresh();
        }
        $body = json_encode($delivery->payload, JSON_THROW_ON_ERROR);
        try {
            $response = Http::timeout(5)->withHeaders(['X-Marchando-Signature' => hash_hmac('sha256', $body, $endpoint->signing_secret), 'Content-Type' => 'application/json'])->withBody($body, 'application/json')->post($endpoint->url);
            $delivery->update(['attempts' => $delivery->attempts + 1, 'response_code' => $response->status(), 'status' => $response->successful() ? 'sent' : 'error', 'next_retry_at' => $response->successful() ? null : now()->addMinutes(15)]);
        } catch (\Throwable) {
            $delivery->update(['attempts' => $delivery->attempts + 1, 'status' => 'error', 'next_retry_at' => now()->addMinutes(15)]);
        }

        return $delivery->fresh();
    }

    public function retryDue(int $limit = 50): int
    {
        $due = OutboundWebhookDelivery::query()->whereIn('status', ['pending', 'error'])->where(fn ($q) => $q->whereNull('next_retry_at')->orWhere('next_retry_at', '<=', now()))->orderBy('id')->limit($limit)->get();
        foreach ($due as $delivery) {
            $this->attempt($delivery);
        }

        return $due->count();
    }
}
