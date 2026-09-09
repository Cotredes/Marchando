<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\User;

class EmployeePolicy
{
    public function view(User $user, Employee $employee): bool
    {
        return $user->restaurants()->whereKey($employee->restaurant_id)->exists();
    }

    public function manage(User $user, Employee $employee): bool
    {
        return $user->restaurants()->whereKey($employee->restaurant_id)->wherePivot('role', 'owner')->exists();
    }
}
