<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_platform_owner' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Única fuente de verdad para la propiedad global de Marchando.
     *
     * Ningún controlador, vista, policy o middleware debe comparar emails:
     * toda la autorización global pasa por este método (vía Gate::before y
     * el middleware platform.owner). El flag sobrevive a cambios de email.
     */
    public function isPlatformOwner(): bool
    {
        return (bool) $this->is_platform_owner;
    }

    public function restaurants(): BelongsToMany
    {
        return $this->belongsToMany(Restaurant::class, 'memberships')
            ->using(Membership::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }
}
