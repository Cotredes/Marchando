<?php

namespace App\Models;

use Database\Factories\MembershipFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\Pivot;

class Membership extends Pivot
{
    /** @use HasFactory<MembershipFactory> */
    use HasFactory;

    protected $table = 'memberships';

    protected $fillable = [
        'restaurant_id',
        'user_id',
        'role',
    ];
}
