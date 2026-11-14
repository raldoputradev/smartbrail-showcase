<?php

use App\Models\MesinIot;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $rows = DB::table('mesin_iot')
            ->where('tipe', 'loker')
            ->get(['id', 'locker_slots']);

        foreach ($rows as $row) {
            $slots = json_decode((string) ($row->locker_slots ?? ''), true);
            if (!is_array($slots)) {
                $slots = MesinIot::defaultLockerSlots();
            } else {
                $slots = array_replace(MesinIot::defaultLockerSlots(), $slots);
            }

            foreach ([5, 6] as $slotNo) {
                $key = (string) $slotNo;
                $slots[$key] = array_merge(
                    $slots[$key] ?? [],
                    [
                        'slot_state' => 'maintenance',
                        'note' => 'Dinonaktifkan sementara untuk integrasi firmware 4 relay.',
                    ]
                );
            }

            DB::table('mesin_iot')
                ->where('id', $row->id)
                ->update(['locker_slots' => json_encode($slots)]);
        }
    }

    public function down(): void
    {
        $rows = DB::table('mesin_iot')
            ->where('tipe', 'loker')
            ->get(['id', 'locker_slots']);

        foreach ($rows as $row) {
            $slots = json_decode((string) ($row->locker_slots ?? ''), true);
            if (!is_array($slots)) {
                continue;
            }

            foreach ([5, 6] as $slotNo) {
                $key = (string) $slotNo;
                $slot = $slots[$key] ?? null;
                if (!is_array($slot)) {
                    continue;
                }

                $note = (string) ($slot['note'] ?? '');
                if (($slot['slot_state'] ?? null) === 'maintenance'
                    && $note === 'Dinonaktifkan sementara untuk integrasi firmware 4 relay.') {
                    $slots[$key]['slot_state'] = 'ready';
                    $slots[$key]['note'] = null;
                }
            }

            DB::table('mesin_iot')
                ->where('id', $row->id)
                ->update(['locker_slots' => json_encode($slots)]);
        }
    }
};

