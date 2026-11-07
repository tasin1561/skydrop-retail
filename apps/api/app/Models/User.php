<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'phone', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements Contracts\PanelUser
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'points_balance' => 'integer',
        ];
    }

    /** Filament: only staff (is_admin) may open the admin panel. */
    public function canAccessPanel(mixed $panel): bool
    {
        return (bool) $this->is_admin;
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /** @return array{id: int, name: string, phone: ?string, email: ?string, points: int} */
    public function toApi(): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'phone' => $this->phone, 'email' => $this->email, 'points' => (int) $this->points_balance];
    }
}
