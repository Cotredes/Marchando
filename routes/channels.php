<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('restaurant.{restaurantId}.operations', function (User $user, int $restaurantId): bool {
    return $user->isPlatformOwner() || $user->restaurants()->whereKey($restaurantId)->exists();
});
