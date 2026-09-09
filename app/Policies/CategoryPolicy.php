<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\User;

class CategoryPolicy
{
    public function view(User $user, Category $category): bool
    {
        return $user->restaurants()->whereKey($category->restaurant_id)->exists();
    }

    public function manage(User $user, Category $category): bool
    {
        return $user->restaurants()->whereKey($category->restaurant_id)->wherePivot('role', 'owner')->exists();
    }
}
