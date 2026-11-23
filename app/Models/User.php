<?php

namespace App\Models;

use App\Enums\Role;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery
{
    use HasFactory, HasRoles, Notifiable;

    protected $fillable = ['name', 'first_name', 'last_name', 'email', 'password', 'phone', 'address', 'city', 'internal_notes', 'is_active', 'must_set_password'];

    /** Valori implicite si pentru modelele abia create, inainte de a fi recitite din baza de date. */
    protected $attributes = ['is_active' => true, 'must_set_password' => false, 'app_authentication_secret' => null, 'app_authentication_recovery_codes' => null,
        'wp_password_hash' => null, 'wp_user_id' => null, 'wc_customer_id' => null, 'first_name' => null, 'last_name' => null, 'orders_count' => 0, 'total_spent' => 0, 'phone' => null, 'address' => null, 'city' => null, 'internal_notes' => null, 'last_login_at' => null, 'marketing_opt_in_at' => null];

    protected $hidden = ['password', 'remember_token', 'app_authentication_secret', 'app_authentication_recovery_codes', 'wp_password_hash'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'marketing_opt_in_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'must_set_password' => 'boolean',
            'app_authentication_secret' => 'encrypted',
            'app_authentication_recovery_codes' => 'encrypted:array',
        ];
    }

    public function isStaff(): bool
    {
        return $this->hasAnyRole(Role::staff());
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_active && $this->isStaff();
    }

    public function getAppAuthenticationSecret(): ?string
    {
        return $this->app_authentication_secret;
    }

    public function saveAppAuthenticationSecret(?string $secret): void
    {
        $this->app_authentication_secret = $secret;
        $this->save();
    }

    public function getAppAuthenticationHolderName(): string
    {
        return $this->email;
    }

    public function getAppAuthenticationRecoveryCodes(): ?array
    {
        return $this->app_authentication_recovery_codes;
    }

    public function saveAppAuthenticationRecoveryCodes(?array $codes): void
    {
        $this->app_authentication_recovery_codes = $codes;
        $this->save();
    }

    public function isCustomer(): bool
    {
        return ! $this->isStaff();
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class)->latest('id');
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    public function wishlist(): HasMany
    {
        return $this->hasMany(WishlistItem::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /** Recalculeaza numarul de comenzi si totalul cheltuit (doar comenzile care conteaza ca vanzare). */
    public function refreshOrderStats(): void
    {
        $sales = $this->orders()->get()->filter(fn (Order $o) => $o->status->countsAsSale());
        $this->forceFill(['orders_count' => $sales->count(), 'total_spent' => (int) $sales->sum(fn ($o) => $o->total - $o->refunded_total)])->saveQuietly();
    }
}
