<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    public function view(User $user, Order $order): bool
    {
        return $user->restaurants()->whereKey($order->restaurant_id)->exists();
    }

    public function operate(User $user, Order $order): bool
    {
        return $this->view($user, $order);
    }
}
