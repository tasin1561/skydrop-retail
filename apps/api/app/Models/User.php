<?php

namespace App\Models;

use App\Support\Rbac\PermissionResolver;
use Database\Factories\UserFactory;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'phone', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements Contracts\PanelUser, HasAppAuthentication, HasAppAuthenticationRecovery
{
    /** @use HasFactory<UserFactory> */
    use Concerns\HasAdminTwoFactor, Concerns\HasRoles, HasApiTokens, HasFactory, Notifiable; // HasAdminTwoFactor: admin TOTP (security module)

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'blocked_at' => 'datetime',
            'points_balance' => 'integer',
            'notify_prefs' => 'array', // messaging module: marketing opt-outs (App\Services\Messaging\Prefs)
        ];
    }

    /** Filament: staff (is_admin or any role, see App\Support\Rbac) who are not blocked may open the admin panel. */
    public function canAccessPanel(mixed $panel): bool
    {
        return app(PermissionResolver::class)->isStaff($this);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /** @return array{id: int, name: string, phone: ?string, email: ?string, points: int, locale: string} */
    public function toApi(): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'phone' => $this->phone, 'email' => $this->email, 'points' => (int) $this->points_balance, 'locale' => in_array($this->locale, ['en', 'bn'], true) ? $this->locale : 'en'];
    }
}
