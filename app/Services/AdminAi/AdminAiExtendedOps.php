<?php

namespace App\Services\AdminAi;

use App\Models\AbsensiAlfa;
use App\Models\AbsensiHarian;
use App\Models\AbsensiTidakLengkap;
use App\Models\LogAbsensi;
use App\Models\MesinIot;
use App\Models\Suggestion;
use App\Models\User;
use App\Support\PendingAccountApproval;
use App\Support\PeriodicReportBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Operasi tambahan Asisten AI (attention, laporan, absensi sync, loker slot).
 */
final class AdminAiExtendedOps
{
    /**
     * @return array<string, mixed>
     */
    public static function attentionToday(): array
    {
        $tanggal = now()->toDateString();
        $pending = PendingAccountApproval::count();
        $saran = Suggestion::query()->where('status', 'baru')->count();
        $alfa = AbsensiAlfa::query()->where('aktif', true)->whereDate('tanggal', $tanggal)->count();
        $tl = AbsensiTidakLengkap::query()
            ->where('selesai', false)
            ->whereDate('tanggal', $tanggal)
            ->count();

        $mesin = MesinIot::query()->where('is_active', true)->get();
        $offline = $mesin->filter(fn (MesinIot $m) => ! $m->isOnline())->count();

        $enrollBelum = User::query()
            ->where('role', 'mahasiswa')
            ->where('is_active', true)
            ->get()
            ->filter(fn (User $u) => ! $u->hasFingerprintEnrolled())
            ->count();

        $items = [];
        if ($pending > 0) {
            $items[] = ['level' => 'high', 'kode' => 'pending_approval', 'teks' => "{$pending} akun siap approval"];
        }
        if ($saran > 0) {
            $items[] = ['level' => 'medium', 'kode' => 'saran_baru', 'teks' => "{$saran} saran baru belum ditanggapi"];
        }
        if ($alfa > 0) {
            $items[] = ['level' => 'high', 'kode' => 'alfa', 'teks' => "{$alfa} alfa aktif hari ini ({$tanggal})"];
        }
        if ($tl > 0) {
            $items[] = ['level' => 'medium', 'kode' => 'tidak_lengkap', 'teks' => "{$tl} kasus tidak lengkap belum selesai"];
        }
        if ($offline > 0) {
            $items[] = ['level' => 'high', 'kode' => 'mesin_offline', 'teks' => "{$offline} mesin aktif offline"];
        }
        if ($enrollBelum > 0) {
            $items[] = ['level' => 'low', 'kode' => 'enroll', 'teks' => "{$enrollBelum} mahasiswa aktif belum enroll fingerprint"];
        }
        $tap = (int) LogAbsensi::query()->whereDate('waktu_tap', $tanggal)->count();

        return [
            'generated_at' => now()->toDateTimeString(),
            'tanggal' => $tanggal,
            'ok' => $items === [],
            'prioritas' => $items,
            'aktivitas_hari_ini' => [
                'tap_absensi' => $tap,
            ],
            'saran_tindak' => $items === []
                ? ['Tidak ada item prioritas — lab relatif tenang.']
                : collect($items)->take(5)->map(fn ($i) => 'Tindak: '.$i['teks'])->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    public static function laporanPeriodikRingkas(array $args): array
    {
        $periode = (string) ($args['periode'] ?? 'minggu_ini');
        if (! in_array($periode, ['minggu_ini', 'minggu_lalu', 'bulan_ini', 'bulan_lalu'], true)) {
            $periode = 'minggu_ini';
        }
        $rekor = (string) ($args['rekor'] ?? 'terajin');
        if (! in_array($rekor, ['terajin', 'alfa', 'jarang', 'tidak_lengkap'], true)) {
            $rekor = 'terajin';
        }

        $request = Request::create('/admin/ai', 'GET', [
            'periode' => $periode,
            'aktivitas' => 'absensi',
            'rekor' => $rekor,
            'rekor_group' => 'semua',
            'kelas' => (string) ($args['kelas'] ?? ''),
            'tim' => (string) ($args['tim'] ?? ''),
        ]);

        $full = PeriodicReportBuilder::build($request, null);
        $summary = $full['summary'] ?? [];
        $rankings = $full['rankings'] ?? [];
        $items = array_slice($rankings['items'] ?? [], 0, 8);

        return [
            'generated_at' => now()->toDateTimeString(),
            'periode' => $periode,
            'filter' => [
                'kelas' => (string) ($args['kelas'] ?? ''),
                'tim' => (string) ($args['tim'] ?? ''),
                'rekor' => $rekor,
            ],
            'summary' => [
                'mahasiswa' => $summary['mahasiswa'] ?? null,
                'hadir' => $summary['hadir'] ?? 0,
                'tidak_lengkap' => $summary['tidak_lengkap'] ?? 0,
                'alfa' => $summary['alfa'] ?? 0,
                'loker' => $summary['loker'] ?? 0,
            ],
            'ranking' => [
                'title' => $rankings['title'] ?? 'Peringkat',
                'metric' => $rankings['metric'] ?? $rekor,
                'items' => collect($items)->map(fn ($row) => [
                    'rank' => $row['rank'] ?? null,
                    'label' => $row['label'] ?? '-',
                    'meta' => $row['meta'] ?? null,
                    'value_label' => $row['value_label'] ?? ($row['value'] ?? null),
                    'user_id' => $row['user_id'] ?? null,
                ])->all(),
            ],
            'catatan' => 'Ringkasan tanpa file export. Absensi (hadir/alfa/TL) terpisah dari hitungan akses loker.',
        ];
    }

    public static function syncAbsensiAfterKoreksi(AbsensiHarian $harian, string $status, int $staffId): void
    {
        $base = [
            'user_id' => $harian->user_id,
            'tanggal' => $harian->tanggal,
            'sesi' => $harian->sesi,
        ];

        if (in_array($status, ['lengkap', 'sakit', 'izin', 'masuk', 'belum'], true)) {
            AbsensiAlfa::query()
                ->where($base)
                ->where('aktif', true)
                ->update([
                    'aktif' => false,
                    'dibatalkan_oleh' => $staffId,
                    'dibatalkan_at' => now(),
                    'kategori' => in_array($status, ['sakit', 'izin'], true) ? $status : 'alfa',
                ]);
        }

        if ($status === 'alfa') {
            AbsensiAlfa::query()->updateOrCreate($base, [
                'sumber' => 'manual',
                'aktif' => true,
                'kategori' => 'alfa',
                'dibatalkan_at' => null,
                'dibatalkan_oleh' => null,
            ]);
        }

        if ($status === 'tidak_lengkap') {
            AbsensiTidakLengkap::query()->updateOrCreate(
                array_merge($base, ['jenis' => 'tanpa_pulang']),
                ['selesai' => false]
            );
        } elseif (in_array($status, ['lengkap', 'sakit', 'izin', 'alfa', 'belum'], true)) {
            AbsensiTidakLengkap::query()
                ->where($base)
                ->update([
                    'selesai' => true,
                    'ditangani_oleh' => $staffId,
                    'ditangani_at' => now(),
                ]);
        }
    }


    /**
     * @return array{judul: string, isi: string}
     */
    public static function draftPengumumanFromTopik(string $topik, string $target): array
    {
        $topik = trim(Str::limit($topik, 200, ''));
        $judul = Str::limit('Pengumuman: '.$topik, 120, '…');
        $audiens = match ($target) {
            'dosen' => 'dosen',
            'mahasiswa' => 'mahasiswa',
            default => 'seluruh warga lab',
        };

        $isi = "Yth. {$audiens},\n\n"
            ."Berikut informasi terkait: {$topik}.\n\n"
            ."Mohon diperhatikan dan ditindaklanjuti sesuai ketentuan operasional lab AIoT SIMALAS (BRAIL Polibatam).\n\n"
            ."Terima kasih.\n"
            .'Admin Laboran';

        return ['judul' => $judul, 'isi' => $isi];
    }
}
