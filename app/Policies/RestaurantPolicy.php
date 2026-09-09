<?php

namespace App\Policies;

use App\Models\Restaurant;
use App\Models\User;

class RestaurantPolicy
{
    public function viewFloorPlan(User $user, Restaurant $restaurant): bool
    {
        return $user->restaurants()->whereKey($restaurant)->exists();
    }

    public function manageFloorPlan(User $user, Restaurant $restaurant): bool
    {
        return $user->restaurants()->whereKey($restaurant)->wherePivot('role', 'owner')->exists();
    }

    public function viewCatalog(User $user, Restaurant $restaurant): bool
    {
        return $user->restaurants()->whereKey($restaurant)->exists();
    }

    public function manageCatalog(User $user, Restaurant $restaurant): bool
    {
        return $user->restaurants()->whereKey($restaurant)->wherePivot('role', 'owner')->exists();
    }

    public function viewSettings(User $user, Restaurant $restaurant): bool
    {
        return $user->restaurants()->whereKey($restaurant)->exists();
    }

    public function updateSettings(User $user, Restaurant $restaurant): bool
    {
        return $user->restaurants()
            ->whereKey($restaurant)
            ->wherePivot('role', 'owner')
            ->exists();
    }

    public function viewStaff(User $user, Restaurant $restaurant): bool
    {
        return $user->restaurants()->whereKey($restaurant)->exists();
    }

    public function manageStaff(User $user, Restaurant $restaurant): bool
    {
        return $user->restaurants()->whereKey($restaurant)->wherePivot('role', 'owner')->exists();
    }

    public function manageAttendance(User $user, Restaurant $restaurant): bool
    {
        return $this->manageStaff($user, $restaurant);
    }

    public function usePos(User $user, Restaurant $restaurant): bool
    {
        return $user->restaurants()->whereKey($restaurant)->exists();
    }

    public function viewSensitiveOrders(User $user, Restaurant $restaurant): bool
    {
        return $user->restaurants()->whereKey($restaurant)->wherePivot('role', 'owner')->exists();
    }

    public function useKitchen(User $user, Restaurant $restaurant): bool
    {
        return $user->restaurants()->whereKey($restaurant)->exists();
    }

    public function manageKitchen(User $user, Restaurant $restaurant): bool
    {
        return $user->restaurants()->whereKey($restaurant)->wherePivot('role', 'owner')->exists();
    }

    public function viewSales(User $user, Restaurant $restaurant): bool
    {
        return $user->restaurants()->whereKey($restaurant)->exists();
    }

    public function viewAnalytics(User $user, Restaurant $restaurant): bool
    {
        return $user->restaurants()->whereKey($restaurant)->exists();
    }

    public function manageBilling(User $user, Restaurant $restaurant): bool
    {
        return $user->restaurants()->whereKey($restaurant)->wherePivot('role', 'owner')->exists();
    }

    public function manageStock(User $user, Restaurant $restaurant): bool
    {
        return $user->restaurants()->whereKey($restaurant)->wherePivot('role', 'owner')->exists();
    }

    public function viewAudit(User $user, Restaurant $restaurant): bool
    {
        return $user->restaurants()->whereKey($restaurant)->wherePivot('role', 'owner')->exists();
    }

    public function manageIntegrations(User $user, Restaurant $restaurant): bool
    {
        return $user->restaurants()->whereKey($restaurant)->wherePivot('role', 'owner')->exists();
    }

    public function viewFiscal(User $user, Restaurant $restaurant): bool
    {
        return $user->restaurants()->whereKey($restaurant)->wherePivot('role', 'owner')->exists();
    }
}
