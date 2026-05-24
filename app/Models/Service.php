<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $name
 * @property string $ticket_prefix
 * @property bool $is_login_required
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Tenant $tenant
 * @property-read EloquentCollection<int, ServiceSchedule> $schedules
 * @property-read EloquentCollection<int, Ticket> $tickets
 */
#[Fillable(['tenant_id', 'name', 'ticket_prefix', 'is_login_required'])]
class Service extends Model
{
    use HasFactory;

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(ServiceSchedule::class);
    }

    public function tickets(): HasManyThrough
    {
        return $this->hasManyThrough(Ticket::class, ServiceSchedule::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_login_required' => 'boolean',
        ];
    }
}
