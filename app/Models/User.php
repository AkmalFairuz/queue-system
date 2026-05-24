<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read EloquentCollection<int, Tenant> $ownedTenants
 * @property-read EloquentCollection<int, Tenant> $adminTenants
 * @property-read EloquentCollection<int, Tenant> $tenantMemberships
 * @property-read EloquentCollection<int, Counter> $assignedCounters
 * @property-read EloquentCollection<int, Ticket> $tickets
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public function ownedTenants(): HasMany
    {
        return $this->hasMany(Tenant::class, 'owner_id');
    }

    public function adminTenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'tenant_user')
            ->withPivot('role')
            ->wherePivot('role', 'admin')
            ->withTimestamps();
    }

    public function tenantMemberships(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'tenant_user')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function assignedCounters(): BelongsToMany
    {
        return $this->belongsToMany(Counter::class, 'counter_staff', 'staff_id', 'counter_id');
    }

    public function belongsToTenant(Tenant $tenant): bool
    {
        return $tenant->owner_id === $this->id
            || $this->tenantMemberships()->whereKey($tenant->id)->exists();
    }

    public function managesTenant(Tenant $tenant): bool
    {
        return $tenant->owner_id === $this->id
            || $this->adminTenants()->whereKey($tenant->id)->exists();
    }

    public function canAccessCounter(Tenant $tenant, Counter $counter): bool
    {
        if ($counter->tenant_id !== $tenant->id) {
            return false;
        }

        return $this->managesTenant($tenant)
            || $this->assignedCounters()->whereKey($counter->id)->exists();
    }

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
        ];
    }
}
