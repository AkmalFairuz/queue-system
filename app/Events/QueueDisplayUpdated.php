<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class QueueDisplayUpdated implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $tenantId,
        public string $reason,
        public ?int $counterId = null,
        public ?int $ticketId = null,
    ) {
    }

    public function broadcastOn(): Channel
    {
        return new Channel('tenant.'.$this->tenantId.'.display');
    }

    public function broadcastAs(): string
    {
        return 'queue.display.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'tenant_id' => $this->tenantId,
            'reason' => $this->reason,
            'counter_id' => $this->counterId,
            'ticket_id' => $this->ticketId,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
