<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Kejadian akses pintu (buka/tolak) — dipancarkan ke kanal staf agar
 * dashboard laboran/admin melihat aktivitas pintu realtime.
 */
class RuanganAksesTercatat implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public array $data,
        public string $deliveryId,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('absensi.staff.laboran')];
    }

    public function broadcastAs(): string
    {
        return 'ruangan.akses';
    }

    public function broadcastWith(): array
    {
        return $this->data + ['delivery_id' => $this->deliveryId];
    }
}
