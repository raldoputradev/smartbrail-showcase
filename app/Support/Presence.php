<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Perkiraan "pengguna online" = akun dengan sesi yang masih aktif
 * (last_activity dalam ambang menit). Memakai tabel sessions bawaan
 * (SESSION_DRIVER=database) — tanpa heartbeat tambahan.
 */
class Presence
{
    public const WINDOW_MINUTES = 5;

    public static function onlineUsers(int $minutes = self::WINDOW_MINUTES): int
    {
        return (int) DB::table('sessions')
            ->whereNotNull('user_id')
            ->where('last_activity', '>=', now()->subMinutes($minutes)->getTimestamp())
            ->distinct()
            ->count('user_id');
    }
}
