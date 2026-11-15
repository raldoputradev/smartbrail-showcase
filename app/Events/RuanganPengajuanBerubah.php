<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Perubahan status pengajuan ruangan — dipancarkan ke channel privat
 * setiap penerima (pengaju + peserta) agar UI mahasiswa sinkron realtime.
 * Payload minimal: UI me-reload prop Inertia setelah menerima sinyal.
 */
class RuanganPengajuanBerubah implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  list<int>  $penerima  user_id penerima (pengaju + peserta)
     */
    public function __construct(
        public int $pengajuanId,
        public string $status,
        public string $ruangan,
        public array $penerima,
        public string $deliveryId,
    ) {}

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        $channels = collect($this->penerima)
            ->unique()
            ->map(fn ($id) => new PrivateChannel('mahasiswa.' . $id))
            ->all();
        // Laboran/admin juga dibangunkan — badge pengajuan & lonceng ikut segar.
        $channels[] = new PrivateChannel('absensi.staff.laboran');

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'pengajuan.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'delivery_id' => $this->deliveryId,
            'pengajuan_id' => $this->pengajuanId,
            'status' => $this->status,
            'ruangan' => $this->ruangan,
        ];
    }
}
