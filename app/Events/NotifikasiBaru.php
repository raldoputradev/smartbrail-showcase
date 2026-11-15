<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Sinyal "ada notifikasi baru" — payload sengaja minimal; klien cukup
 * me-reload prop notif/menu (yang sudah terfilter per-pengguna di server).
 *
 * Dua pola pemakaian:
 *  - kanal `notifikasi.semua`  → pengumuman baru (semua klien menyegarkan loncengnya)
 *  - kanal `mahasiswa.{id}`    → peristiwa tertarget (balasan saran, dsb.)
 */
class NotifikasiBaru implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  list<int>  $userIds  penerima tertarget (kanal mahasiswa.{id}); kosong = hanya kanal bersama
     */
    public function __construct(
        public string $jenis,
        public array $userIds = [],
        public bool $broadcastSemua = false,
        public bool $broadcastStaff = false,
        public ?string $deliveryId = null,
    ) {}

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        $channels = collect($this->userIds)
            ->unique()
            ->map(fn ($id) => new PrivateChannel('mahasiswa.' . $id))
            ->all();
        if ($this->broadcastSemua) {
            $channels[] = new PrivateChannel('notifikasi.semua');
        }
        if ($this->broadcastStaff) {
            $channels[] = new PrivateChannel('absensi.staff.laboran');
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'notifikasi.baru';
    }

    public function broadcastWith(): array
    {
        return [
            'delivery_id' => $this->deliveryId ?? (string) \Illuminate\Support\Str::uuid(),
            'jenis' => $this->jenis,
        ];
    }
}
