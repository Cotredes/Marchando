<?php

namespace App\Events;

use App\Models\PublicOrderRequest;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PublicOrderChanged implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public PublicOrderRequest $request) {}

    public function broadcastOn(): array
    {
        return [new Channel('public-order.'.$this->request->public_token_hash)];
    }

    public function broadcastAs(): string
    {
        return 'public.order.changed';
    }

    public function broadcastWith(): array
    {
        return ['revision' => $this->request->version];
    }
}
