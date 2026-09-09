<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

class ProductPolicy
{
    public function view(User $user, Product $product): bool
    {
        return $user->restaurants()->whereKey($product->restaurant_id)->exists();
    }

    public function manage(User $user, Product $product): bool
    {
        return $user->restaurants()->whereKey($product->restaurant_id)->wherePivot('role', 'owner')->exists();
    }
}
