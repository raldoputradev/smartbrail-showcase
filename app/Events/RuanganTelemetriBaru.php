<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Snapshot telemetri ruangan baru dari edge — dipancarkan ke kanal staf
 * agar panel Kondisi Ruangan menyegarkan tanpa menunggu polling.
 */
class RuanganTelemetriBaru implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public int $ruanganId) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('absensi.staff.laboran')];
    }

    public function broadcastAs(): string
    {
        return 'ruangan.telemetri';
    }

    public function broadcastWith(): array
    {
        return ['ruangan_id' => $this->ruanganId];
    }
}
