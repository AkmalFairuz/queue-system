<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $service_id
 * @property int $day
 * @property string|null $opens_at
 * @property string|null $closes_at
 * @property int $pre_queue_minutes
 * @property int|null $max_tickets
 * @property bool $is_available
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Service $service
 * @property-read EloquentCollection<int, Ticket> $tickets
 */
#[Fillable([
    'service_id',
    'day',
    'opens_at',
    'closes_at',
    'pre_queue_minutes',
    'max_tickets',
    'is_available',
])]
class ServiceSchedule extends Model
{
    use HasFactory;

    public function isOpenAt(Carbon $time): bool
    {
        if (! $this->is_available || $time->dayOfWeekIso - 1 !== $this->day) {
            return false;
        }

        $serviceDate = $time->copy()->startOfDay();
        $opensAt = $this->opensAtForDate($serviceDate);
        $closesAt = $this->closesAtForDate($serviceDate);

        return $time->greaterThanOrEqualTo($opensAt) && $time->lessThanOrEqualTo($closesAt);
    }

    /**
     * @return array{service_date: Carbon, queue_opens_at: Carbon, opens_at: Carbon, closes_at: Carbon, is_pre_queue: bool}|null
     */
    public function queueContextAt(Carbon $time): ?array
    {
        if (! $this->is_available) {
            return null;
        }

        $serviceDate = $this->nextServiceDateFrom($time);
        $opensAt = $this->opensAtForDate($serviceDate);
        $closesAt = $this->closesAtForDate($serviceDate);
        $queueOpensAt = $opensAt->copy()->subMinutes($this->pre_queue_minutes);

        if ($time->lt($queueOpensAt) || $time->gt($closesAt)) {
            return null;
        }

        return [
            'service_date' => $serviceDate,
            'queue_opens_at' => $queueOpensAt,
            'opens_at' => $opensAt,
            'closes_at' => $closesAt,
            'is_pre_queue' => $time->lt($opensAt),
        ];
    }

    public function nextServiceDateFrom(Carbon $time): Carbon
    {
        $daysUntil = ($this->day - ($time->dayOfWeekIso - 1) + 7) % 7;

        return $time->copy()->startOfDay()->addDays($daysUntil);
    }

    public function opensAtForDate(Carbon $serviceDate): Carbon
    {
        $opensAt = $serviceDate->copy()->startOfDay();

        if ($this->opens_at !== null) {
            $opensAt->setTimeFromTimeString($this->opens_at);
        }

        return $opensAt;
    }

    public function closesAtForDate(Carbon $serviceDate): Carbon
    {
        $closesAt = $serviceDate->copy()->endOfDay();

        if ($this->closes_at !== null) {
            $closesAt->setTimeFromTimeString($this->closes_at);
        }

        return $closesAt;
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'day' => 'integer',
            'pre_queue_minutes' => 'integer',
            'max_tickets' => 'integer',
            'is_available' => 'boolean',
        ];
    }
}
