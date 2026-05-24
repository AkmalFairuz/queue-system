<?php

namespace App\Models;

use App\Support\TicketStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $user_id
 * @property int $tenant_id
 * @property int $service_schedule_id
 * @property int|null $counter_id
 * @property TicketStatus $status
 * @property int $sequence
 * @property Carbon $service_date
 * @property Carbon|null $called_at
 * @property Carbon|null $serving_started_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $cancelled_at
 * @property Carbon|null $skipped_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $user
 * @property-read Tenant $tenant
 * @property-read ServiceSchedule $serviceSchedule
 * @property-read Counter|null $counter
 */
#[Fillable([
    'user_id',
    'tenant_id',
    'service_schedule_id',
    'counter_id',
    'status',
    'sequence',
    'service_date',
    'called_at',
    'serving_started_at',
    'completed_at',
    'cancelled_at',
    'skipped_at',
])]
class Ticket extends Model
{
    use HasFactory;

    public function queueNumber(): string
    {
        return sprintf('%s-%d', $this->queuePrefix(), $this->sequence);
    }

    public function queueNumberForTts(): string
    {
        return sprintf('%s %d', $this->queuePrefix(), $this->sequence);
    }

    private function queuePrefix(): string
    {
        $schedule = $this->relationLoaded('serviceSchedule')
            ? $this->serviceSchedule
            : $this->serviceSchedule()->with('service')->first();

        $service = $schedule?->relationLoaded('service')
            ? $schedule->service
            : $schedule?->service()->first();

        $prefix = $service?->ticket_prefix ?? 'Q';

        return $prefix;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function serviceSchedule(): BelongsTo
    {
        return $this->belongsTo(ServiceSchedule::class);
    }

    public function counter(): BelongsTo
    {
        return $this->belongsTo(Counter::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TicketStatus::class,
            'sequence' => 'integer',
            'service_date' => 'date',
            'called_at' => 'datetime',
            'serving_started_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'skipped_at' => 'datetime',
        ];
    }
}
