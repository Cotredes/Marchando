<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class KitchenChanged implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public int $restaurantId, public int $cursor) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('restaurant.'.$this->restaurantId.'.operations')];
    }

    public function broadcastAs(): string
    {
        return 'kitchen.changed';
    }

    public function broadcastWith(): array
    {
        return ['restaurant_id' => $this->restaurantId, 'cursor' => $this->cursor];
    }
}
