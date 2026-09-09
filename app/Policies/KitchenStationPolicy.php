<?php

namespace App\Policies;

use App\Models\KitchenStation;
use App\Models\User;

class KitchenStationPolicy
{
    public function view(User $user, KitchenStation $station): bool
    {
        return $user->restaurants()->whereKey($station->restaurant_id)->exists();
    }

    public function manage(User $user, KitchenStation $station): bool
    {
        return $user->restaurants()->whereKey($station->restaurant_id)->wherePivot('role', 'owner')->exists();
    }
}
