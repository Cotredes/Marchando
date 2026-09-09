<?php

namespace App\Policies;

use App\Models\DiningTable;
use App\Models\User;

class DiningTablePolicy
{
    public function view(User $user, DiningTable $table): bool
    {
        return $user->restaurants()->whereKey($table->restaurant_id)->exists();
    }

    public function manage(User $user, DiningTable $table): bool
    {
        return $user->restaurants()->whereKey($table->restaurant_id)->wherePivot('role', 'owner')->exists();
    }
}
