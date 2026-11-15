<?php

namespace App\Console\Commands;

use App\Mail\DailyDigestMail;
use App\Models\AbsensiHarian;
use App\Models\RuanganPengajuan;
use App\Models\User;
use App\Support\AbsensiEffectiveSchedule;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

/**
 * Ringkasan harian via email untuk pengguna yang mengaktifkan
 * preferensi digest_email (Profil → Preferensi).
 */
class SendDailyDigestCommand extends Command
{
    protected $signature = 'notifikasi:digest-harian
        {--date= : Tanggal Y-m-d (default: hari ini WIB)}
        {--user= : Batasi ke satu user id (uji coba)}';

    protected $description = 'Kirim email ringkasan harian (absensi, keputusan pengajuan, notifikasi)';

    public function handle(): int
    {
        $tanggal = Carbon::parse($this->option('date') ?: now('Asia/Jakarta')->toDateString(), 'Asia/Jakarta');

        $users = User::query()
            ->where('role', 'mahasiswa')
            ->where('is_active', true)
            ->whereNotNull('email')
            ->where('preferences->digest_email', true)
            ->when($this->option('user'), fn ($q, $id) => $q->where('id', $id))
            ->get(['id', 'nama_lengkap', 'email', 'shift', 'preferences']);

        $sent = 0;
        foreach ($users as $user) {
            try {
                Mail::to($user->email)->queue($this->buildMail($user, $tanggal));
                $sent++;
            } catch (\Throwable $e) {
                $this->warn("Gagal kirim ke user {$user->id}: {$e->getMessage()}");
            }
        }

        $this->info("Digest terkirim ke {$sent} dari {$users->count()} penerima.");

        return self::SUCCESS;
    }

    private function buildMail(User $user, Carbon $tanggal): DailyDigestMail
    {
        $tz = 'Asia/Jakarta';
        $dateStr = $tanggal->toDateString();
        $rows = [];

        // Absensi hari ini
        $jadwal = AbsensiEffectiveSchedule::resolve($dateStr, $user->shift ?: 'pagi');
        $harian = AbsensiHarian::query()
            ->where('user_id', $user->id)
            ->where('tanggal', $dateStr)
            ->with(['masukLog', 'pulangLog'])
            ->first();

        if ($harian) {
            $masuk = optional($harian->masukLog?->waktu_tap)->timezone($tz)->format('H:i');
            $pulang = optional($harian->pulangLog?->waktu_tap)->timezone($tz)->format('H:i');
            $absen = 'Status: ' . ucfirst((string) $harian->status);
            if ($masuk) {
                $absen .= " — masuk {$masuk}" . ($pulang ? ", pulang {$pulang}" : '');
            }
        } elseif (! ($jadwal['is_wajib'] ?? true)) {
            $absen = 'Tidak ada kewajiban absensi hari ini';
        } else {
            $absen = 'Belum ada tap tercatat hari ini';
        }
        $rows[] = ['label' => 'Absensi hari ini', 'value' => $absen];

        // Keputusan pengajuan ruangan hari ini
        $keputusan = RuanganPengajuan::query()
            ->with('ruangan:id,nama')
            ->where(fn ($q) => $q->where('pengaju_id', $user->id)
                ->orWhereJsonContains('peserta', $user->id))
            ->whereDate('diputus_pada', $dateStr)
            ->latest('diputus_pada')
            ->limit(5)
            ->get()
            ->map(fn (RuanganPengajuan $p) => match ($p->status) {
                RuanganPengajuan::STATUS_DISETUJUI => 'Disetujui',
                RuanganPengajuan::STATUS_DITOLAK => 'Ditolak',
                RuanganPengajuan::STATUS_DIBATALKAN => 'Dibatalkan',
                RuanganPengajuan::STATUS_MENUNGGU_PENERIMAAN => 'Tawaran menunggu respons Anda',
                default => ucfirst((string) $p->status),
            } . ' — ' . ($p->ruangan?->nama ?? 'Ruangan'))
            ->all();

        $rows[] = [
            'label' => 'Pengajuan ruangan',
            'value' => $keputusan ? implode('; ', $keputusan) : 'Tidak ada keputusan baru',
        ];

        $unread = (int) (app(\App\Http\Controllers\StudentPortalController::class)
            ->sharedNotificationState($user)['unread_count'] ?? 0);

        return new DailyDigestMail(
            namaLengkap: (string) $user->nama_lengkap,
            tanggalLabel: $tanggal->translatedFormat('l, d F Y'),
            rows: $rows,
            unreadCount: $unread,
            portalUrl: (string) config('app.url'),
        );
    }
}
