<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Membangunkan snapshot HTTP setelah commit.
 * Payload tidak memuat template, NIM, email, atau sandi.
 */
class AbsensiSnapshotWake implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  list<string>  $surfaces
     */
    public function __construct(
        public string $deliveryId,
        public array $surfaces,
        public string $committedAt,
        public ?string $eventId,
        public string $audience,
    ) {}

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        if ($this->audience === 'laboran') {
            return [new PrivateChannel('absensi.staff.laboran')];
        }

        return [
            new PrivateChannel('absensi.staff.laboran'),
            new PrivateChannel('absensi.staff.dosen'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'snapshot.wake';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'delivery_id' => $this->deliveryId,
            'surfaces' => array_values($this->surfaces),
            'committed_at' => $this->committedAt,
            'event_id' => $this->eventId,
        ];
    }
}
