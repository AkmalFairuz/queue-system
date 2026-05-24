<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $code
 * @property string $tts_language
 * @property string $tts_template
 * @property int $owner_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $owner
 * @property-read EloquentCollection<int, User> $admins
 * @property-read EloquentCollection<int, User> $users
 * @property-read EloquentCollection<int, Service> $services
 * @property-read EloquentCollection<int, Counter> $counters
 * @property-read EloquentCollection<int, Ticket> $tickets
 */
#[Fillable(['name', 'code', 'tts_language', 'tts_template', 'owner_id'])]
class Tenant extends Model
{
    use HasFactory;

    public function scopeOrdered($query)
    {
        return $query->orderBy('name');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function admins(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'tenant_user')
            ->withPivot('role')
            ->wherePivot('role', 'admin')
            ->withTimestamps();
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'tenant_user')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    public function counters(): HasMany
    {
        return $this->hasMany(Counter::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function isManagedBy(User $user): bool
    {
        return $this->owner_id === $user->id
            || $this->admins()->whereKey($user->id)->exists();
    }

    public function hasUser(User $user): bool
    {
        return $this->owner_id === $user->id
            || $this->users()->whereKey($user->id)->exists();
    }

    public function renderTtsTemplate(string $queue, ?string $counter = null): string
    {
        return strtr($this->tts_template, [
            '{queue}' => $queue,
            '{counter}' => $counter ?? '',
        ]);
    }
}
