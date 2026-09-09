<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Zone;

class ZonePolicy
{
    public function view(User $user, Zone $zone): bool
    {
        return $user->restaurants()->whereKey($zone->restaurant_id)->exists();
    }

    public function manage(User $user, Zone $zone): bool
    {
        return $user->restaurants()->whereKey($zone->restaurant_id)->wherePivot('role', 'owner')->exists();
    }
}
