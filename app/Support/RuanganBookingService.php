<?php

namespace App\Support;

use App\Models\PblProject;
use App\Models\PblTeamMember;
use App\Models\Ruangan;
use App\Models\RuanganIzin;
use App\Models\RuanganPengajuan;
use App\Models\RuanganPetugas;
use App\Models\User;
use App\Events\RuanganPengajuanBerubah;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Alur pengajuan ruangan SMART BRAIL.
 * Semua batas (H+, durasi, pending/tim) berasal dari aturan ruangan — bukan hard-code.
 */
class RuanganBookingService
{
    /** Tim (PblProject) tempat mahasiswa menjadi anggota. */
    public static function timMahasiswa(User $mahasiswa): ?PblProject
    {
        if (!$mahasiswa->isMahasiswa()) {
            return null;
        }

        $member = PblTeamMember::query()
            ->where('user_id', $mahasiswa->id)
            ->with('project')
            ->first();

        if ($member?->project) {
            return $member->project;
        }

        // Kompatibilitas lama: users.tim = nama proyek.
        if ($mahasiswa->tim) {
            return PblProject::where('nama', $mahasiswa->tim)->first();
        }

        return null;
    }

    /** Anggota tim untuk pilihan peserta (id => nama). */
    public static function anggotaTim(PblProject $project): \Illuminate\Support\Collection
    {
        $ids = PblTeamMember::where('pbl_project_id', $project->id)->pluck('user_id');

        return User::where('role', 'mahasiswa')
            ->where(function ($q) use ($project, $ids) {
                $q->whereIn('id', $ids)->orWhere('tim', $project->nama);
            })
            ->orderBy('nama_lengkap')
            ->get(['id', 'nama_lengkap', 'nitk_nidn', 'profile_photo_path']);
    }

    /** Proyek yang dibimbing dosen (manpro/co-manpro). */
    public static function timBimbingan(User $dosen): \Illuminate\Support\Collection
    {
        if (!$dosen->isDosen()) {
            return collect();
        }

        return PblProject::query()
            ->where('manpro_user_id', $dosen->id)
            ->orWhere('co_manpro_user_id', $dosen->id)
            ->get();
    }

    /**
     * Validasi aturan ruangan. Melempar ValidationException berisi alasan jelas.
     */
    public static function validateSlot(Ruangan $ruangan, Carbon $tanggal, string $mulai, string $selesai, ?PblProject $project, ?int $abaikanPengajuanId = null): void
    {
        $aturan = $ruangan->aturan();
        $errors = [];

        if (!$ruangan->aktif) {
            $errors[] = 'Ruangan sedang tidak tersedia.';
        }

        $hariIso = (int) $tanggal->dayOfWeekIso; // 1..7
        $allowedDays = array_map('intval', $aturan['hari'] ?? []);
        if ($allowedDays !== [] && !in_array($hariIso, $allowedDays, true)) {
            $errors[] = 'Ruangan tidak tersedia pada hari tersebut.';
        }

        $minAdvance = (int) ($aturan['min_advance_days'] ?? 0);
        $maxAdvance = (int) $aturan['max_advance_days'];
        if ($tanggal->isPast() && !$tanggal->isToday()) {
            $errors[] = 'Tanggal pengajuan sudah lewat.';
        }
        // Batas minimum: pengajuan paling lambat H-{min} sebelum pemakaian.
        $palingCepat = Carbon::today()->addDays($minAdvance);
        if ($minAdvance > 0 && !$tanggal->isPast() && $tanggal->lt($palingCepat)) {
            $errors[] = "Pengajuan paling lambat H-{$minAdvance} sebelum pemakaian — pilih tanggal mulai "
                . $palingCepat->translatedFormat('d M Y') . '.';
        }
        if ($tanggal->gt(Carbon::today()->addDays($maxAdvance))) {
            $errors[] = "Pemesanan maksimal H+{$maxAdvance} ke depan.";
        }

        if ($mulai >= $selesai) {
            $errors[] = 'Jam selesai harus setelah jam mulai.';
        }
        if ($mulai < $aturan['jam_buka'] || $selesai > $aturan['jam_tutup']) {
            $errors[] = "Jam harus dalam rentang {$aturan['jam_buka']}–{$aturan['jam_tutup']}.";
        }

        // Batas durasi: maks. 5 jam secara kebijakan, dan tidak melebihi batas ruangan.
        $maksMenit = min(300, (int) $aturan['max_duration_minutes']);
        $durasi = Carbon::parse($mulai)->diffInMinutes(Carbon::parse($selesai));
        if ($durasi > $maksMenit) {
            $errors[] = 'Durasi peminjaman maksimal ' . intdiv($maksMenit, 60) . ' jam per pengajuan.';
        }

        // Selaras dengan status slot 'dipesan': rentang yang sudah terpakai
        // pengajuan disetujui/menunggu penerimaan tidak dapat diajukan lagi.
        $bentrok = RuanganPengajuan::where('ruangan_id', $ruangan->id)
            ->whereDate('tanggal', $tanggal->toDateString())
            ->whereIn('status', [RuanganPengajuan::STATUS_DISETUJUI, RuanganPengajuan::STATUS_MENUNGGU_PENERIMAAN])
            ->when($abaikanPengajuanId, fn ($q) => $q->where('id', '!=', $abaikanPengajuanId))
            ->where('jam_mulai', '<', $selesai)
            ->where('jam_selesai', '>', $mulai)
            ->exists();
        if ($bentrok) {
            $errors[] = 'Waktu bertabrakan dengan peminjaman yang sudah disetujui.';
        }

        // Jadwal petugas (cleaning service) dapat memblokir pemesanan.
        foreach (self::blokirPetugas($ruangan->id, $tanggal) as [$bMulai, $bSelesai]) {
            if ($mulai < $bSelesai && $selesai > $bMulai) {
                $errors[] = "Waktu bertabrakan dengan jadwal petugas ({$bMulai}–{$bSelesai}).";
            }
        }

        // Maksimal satu pengajuan menunggu per tim per ruangan.
        if ($project) {
            $pending = RuanganPengajuan::where('ruangan_id', $ruangan->id)
                ->where('pbl_project_id', $project->id)
                ->whereIn('status', RuanganPengajuan::STATUS_AKTIF_MENUNGGU)
                ->when($abaikanPengajuanId, fn ($q) => $q->where('id', '!=', $abaikanPengajuanId))
                ->whereDate('tanggal', '>=', Carbon::today())
                ->count();
            if ($pending >= (int) $aturan['max_pending_per_team']) {
                $errors[] = 'Tim sudah memiliki pengajuan menunggu untuk ruangan ini.';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages(['pengajuan' => $errors]);
        }
    }

    /**
     * Ketersediaan satu ruangan untuk satu tanggal + durasi — sumber tunggal
     * untuk kartu pemilih waktu dan endpoint JSON mahasiswa.
     * Tidak mengembalikan identitas pemesan lain (hanya rentang + jenis blokir).
     */
    public static function ketersediaanHarian(Ruangan $ruangan, Carbon $tanggal, int $durasiMenit): array
    {
        $aturan = $ruangan->aturan();
        $tz = 'Asia/Jakarta';
        $now = now($tz);
        $tanggal = $tanggal->copy()->setTimezone($tz)->startOfDay();

        $alasan = null;
        $allowedDays = array_map('intval', $aturan['hari'] ?? []);
        if (!$ruangan->aktif) {
            $alasan = 'Ruangan ini sedang ditutup sementara dan tidak dapat diajukan.';
        } elseif ($tanggal->lt(Carbon::today($tz))) {
            $alasan = 'Tanggal sudah lewat — pilih hari ini atau tanggal berikutnya.';
        } elseif ((int) ($aturan['min_advance_days'] ?? 0) > 0 && $tanggal->lt(Carbon::today($tz)->addDays((int) $aturan['min_advance_days']))) {
            $palingCepat = Carbon::today($tz)->addDays((int) $aturan['min_advance_days']);
            $alasan = 'Pengajuan paling lambat H-' . (int) $aturan['min_advance_days']
                . ' sebelum pemakaian — pilih tanggal mulai '
                . $palingCepat->locale('id')->isoFormat('dddd, D MMMM Y') . '.';
        } elseif ($tanggal->gt(Carbon::today($tz)->addDays((int) $aturan['max_advance_days']))) {
            $batas = Carbon::today($tz)->addDays((int) $aturan['max_advance_days']);
            $alasan = 'Tanggal di luar batas pengajuan — pilih tanggal paling lambat '
                . $batas->locale('id')->isoFormat('dddd, D MMMM Y')
                . " (maks. H+{$aturan['max_advance_days']}).";
        } elseif ($allowedDays !== [] && !in_array((int) $tanggal->dayOfWeekIso, $allowedDays, true)) {
            $alasan = 'Ruangan tidak beroperasi pada '
                . $tanggal->locale('id')->isoFormat('dddd, D MMMM Y')
                . '. Silakan pilih tanggal lain.';
        }

        // Terhalang: pengajuan disetujui/menunggu-penerimaan + jadwal petugas.
        // Slot terisi membawa info pemesan agar tim lain tahu ruangan dipakai siapa.
        $terhalang = [];
        if ($alasan === null) {
            RuanganPengajuan::where('ruangan_id', $ruangan->id)
                ->whereDate('tanggal', $tanggal->toDateString())
                ->whereIn('status', [RuanganPengajuan::STATUS_DISETUJUI, RuanganPengajuan::STATUS_MENUNGGU_PENERIMAAN])
                ->with(['pengaju:id,nama_lengkap', 'project:id,nama,kode'])
                ->get(['id', 'pengaju_id', 'pbl_project_id', 'jam_mulai', 'jam_selesai'])
                ->each(function ($p) use (&$terhalang) {
                    $terhalang[] = [
                        'mulai' => substr((string) $p->jam_mulai, 0, 5),
                        'selesai' => substr((string) $p->jam_selesai, 0, 5),
                        'jenis' => 'dipesan',
                        'info' => $p->project?->nama ?? $p->pengaju?->nama_lengkap ?? 'Tim lain',
                    ];
                });
            foreach (self::blokirPetugas($ruangan->id, $tanggal) as [$m, $s]) {
                $terhalang[] = ['mulai' => $m, 'selesai' => $s, 'jenis' => 'petugas', 'info' => 'Jadwal petugas'];
            }
        }

        // Titik mulai per jam dalam rentang buka–tutup; status memperhitungkan durasi penuh.
        $slots = [];
        if ($alasan === null) {
            $cursor = Carbon::parse($tanggal->toDateString() . ' ' . $aturan['jam_buka'], $tz);
            $tutup = Carbon::parse($tanggal->toDateString() . ' ' . $aturan['jam_tutup'], $tz);
            while ($cursor->copy()->addMinutes($durasiMenit)->lte($tutup)) {
                $mulai = $cursor->format('H:i');
                $selesai = $cursor->copy()->addMinutes($durasiMenit)->format('H:i');
                $status = 'tersedia';
                $info = null;
                foreach ($terhalang as $b) {
                    if ($mulai < $b['selesai'] && $selesai > $b['mulai']) {
                        $status = $b['jenis'];
                        $info = $b['info'] ?? null;
                        break;
                    }
                }
                // Slot hari ini yang waktunya sudah lewat ditandai 'lewat' —
                // tetap bisa diminta (Laboran memutuskan), kecuali sudah terhalang.
                if ($status === 'tersedia' && $tanggal->isToday() && $cursor->lt($now)) {
                    $status = 'lewat';
                }
                $slots[] = ['mulai' => $mulai, 'selesai' => $selesai, 'status' => $status, 'info' => $info];
                $cursor->addHour();
            }
        }

        // Alasan khusus saat boleh=true tetapi tidak ada slot yang bisa dipilih.
        $habisAlasan = null;
        if ($alasan === null && $slots !== [] && !collect($slots)->contains('status', 'tersedia')) {
            $statuses = collect($slots)->pluck('status')->unique();
            $habisAlasan = $statuses->every(fn ($s) => $s === 'lewat')
                ? 'Tidak ada lagi slot yang dapat diajukan pada tanggal ini.'
                : ($statuses->every(fn ($s) => $s === 'dipesan')
                    ? 'Seluruh slot pada tanggal ini sudah dipesan.'
                    : 'Semua slot hari ini sudah terisi atau diblokir — ruangan tidak tersedia. Coba tanggal lain.');
        }

        // Hari operasional berikutnya (maks. 14 hari ke depan) untuk membantu pengguna.
        $tanggalBerikutnya = null;
        if ($alasan !== null) {
            $scan = Carbon::today($tz)->addDay();
            for ($i = 0; $i < 14; $i++, $scan->addDay()) {
                if ($allowedDays === [] || in_array((int) $scan->dayOfWeekIso, $allowedDays, true)) {
                    $tanggalBerikutnya = $scan->toDateString();
                    break;
                }
            }
        }

        return [
            'tanggal' => $tanggal->toDateString(),
            'boleh' => $alasan === null,
            'alasan' => $alasan,
            'habis_alasan' => $habisAlasan,
            'hari_label' => self::labelHari($allowedDays),
            'tanggal_tersedia_berikutnya' => $tanggalBerikutnya,
            'jam_buka' => $aturan['jam_buka'],
            'jam_tutup' => $aturan['jam_tutup'],
            'min_advance_days' => (int) ($aturan['min_advance_days'] ?? 0),
            'max_advance_days' => (int) $aturan['max_advance_days'],
            'max_duration_minutes' => (int) $aturan['max_duration_minutes'],
            'hari' => $allowedDays,
            'slots' => $slots,
        ];
    }

    /** Label hari operasional ringkas: [1,2] → "Senin & Selasa", [1..5] → "Senin–Jumat". */
    public static function labelHari(array $days): ?string
    {
        if ($days === []) {
            return 'Setiap hari';
        }
        static $nama = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'];
        sort($days);
        $urut = true;
        for ($i = 1; $i < count($days); $i++) {
            if ($days[$i] !== $days[$i - 1] + 1) {
                $urut = false;
                break;
            }
        }
        if ($urut && count($days) > 2) {
            return ($nama[$days[0]] ?? '') . '–' . ($nama[end($days)] ?? '');
        }

        return implode(' & ', array_map(fn ($d) => $nama[$d] ?? (string) $d, $days));
    }

    /** Rentang waktu yang diblokir jadwal petugas untuk ruangan+tanggal. */
    public static function blokirPetugas(int $ruanganId, Carbon $tanggal): array
    {
        $blok = [];
        RuanganPetugas::where('status', 'aktif')->whereNotNull('jadwal')->get()->each(function ($p) use ($ruanganId, $tanggal, &$blok) {
            if (!$p->dalamMasaBerlaku($tanggal)) {
                return;
            }
            foreach ((array) $p->jadwal as $j) {
                if ((int) ($j['ruangan_id'] ?? 0) !== $ruanganId) {
                    continue;
                }
                $hari = array_map('intval', (array) ($j['hari'] ?? []));
                if ($hari !== [] && !in_array((int) $tanggal->dayOfWeekIso, $hari, true)) {
                    continue;
                }
                $blok[] = [$j['jam_mulai'] ?? '00:00', $j['jam_selesai'] ?? '23:59'];
            }
        });

        return $blok;
    }

    /**
     * Buat pengajuan dengan snapshot identitas — riwayat tak berubah saat anggota pindah tim.
     */
    public static function ajukan(User $pengaju, Ruangan $ruangan, PblProject $project, array $data): RuanganPengajuan
    {
        // Serialkan pengajuan pada ruangan+tanggal yang sama agar dua submit
        // bersamaan tidak sama-sama lolos cek pending/bentrok.
        $pengajuan = DB::transaction(function () use ($pengaju, $ruangan, $project, $data) {
            $tanggal = Carbon::parse($data['tanggal']);
            self::lockRowsAktif($ruangan->id, $tanggal);
            self::validateSlot($ruangan, $tanggal, $data['jam_mulai'], $data['jam_selesai'], $project);

            $pesertaIds = collect($data['peserta'] ?? [$pengaju->id])
                ->map(fn ($id) => (int) $id)->unique()->values();
            // Peserta wajib anggota tim saat ini.
            $validIds = self::anggotaTim($project)->pluck('id')->map(fn ($i) => (int) $i);
            if ($pesertaIds->diff($validIds)->isNotEmpty()) {
                throw ValidationException::withMessages(['peserta' => 'Peserta harus anggota tim pengaju.']);
            }

            // Batas peserta mengikuti konfigurasi ruangan (aturan.max_peserta / kolom kapasitas).
            $maxPeserta = (int) ($ruangan->aturan()['max_peserta'] ?? 0);
            if ($maxPeserta > 0 && $pesertaIds->count() > $maxPeserta) {
                throw ValidationException::withMessages([
                    'peserta' => "Jumlah peserta melebihi batas ruangan (maks. {$maxPeserta} orang).",
                ]);
            }

            $pembimbing = $project->manpro_user_id ? User::find($project->manpro_user_id) : null;
            $snapshot = [
                'pengaju' => ['id' => $pengaju->id, 'name' => $pengaju->nama_lengkap, 'nim' => $pengaju->nitk_nidn ?? null],
                'tim' => ['id' => $project->id, 'kode' => $project->kode, 'nama' => $project->nama],
                'pembimbing' => $pembimbing ? ['id' => $pembimbing->id, 'name' => $pembimbing->nama_lengkap] : null,
                'peserta' => User::whereIn('id', $pesertaIds)->get(['id', 'nama_lengkap', 'nitk_nidn'])
                    ->map(fn ($u) => ['id' => $u->id, 'name' => $u->nama_lengkap, 'nim' => $u->nitk_nidn ?? null])->all(),
                'ruangan' => ['id' => $ruangan->id, 'kode' => $ruangan->kode, 'nama' => $ruangan->nama],
            ];

            return RuanganPengajuan::create([
                'ruangan_id' => $ruangan->id,
                'pengaju_id' => $pengaju->id,
                'pbl_project_id' => $project->id,
                'pembimbing_id' => $pembimbing?->id,
                'tujuan' => $data['tujuan'],
                'tanggal' => $tanggal->toDateString(),
                'jam_mulai' => $data['jam_mulai'],
                'jam_selesai' => $data['jam_selesai'],
                'peserta' => $pesertaIds->all(),
                'snapshot' => $snapshot,
                'status' => RuanganPengajuan::STATUS_DIAJUKAN,
            ]);
        });
        self::siarkan($pengajuan->fresh());

        return $pengajuan;
    }

    /**
     * Setujui atomik: kunci baris konflik di ruangan+tanggal yang sama agar
     * dua persetujuan bersamaan tidak saling mendahului.
     */
    public static function setujui(RuanganPengajuan $p, User $penyetuju, ?string $alasan = null): void
    {
        DB::transaction(function () use ($p, $penyetuju, $alasan) {
            $p = RuanganPengajuan::lockForUpdate()->findOrFail($p->id);
            if (!in_array($p->status, RuanganPengajuan::STATUS_AKTIF_MENUNGGU, true)) {
                throw ValidationException::withMessages(['status' => 'Pengajuan tidak lagi menunggu keputusan.']);
            }
            self::assertTidakKonflik($p);
            self::validateSlot($p->ruangan, Carbon::parse($p->tanggal), $p->jam_mulai, $p->jam_selesai, $p->project, $p->id);

            $p->update([
                'status' => RuanganPengajuan::STATUS_DISETUJUI,
                'diputus_oleh' => $penyetuju->id,
                'diputus_pada' => now(),
                'alasan_keputusan' => $alasan,
            ]);
            self::terbitkanIzin($p->fresh());
        });
        self::siarkan($p->fresh());
    }

    /**
     * Konflik: overlap dengan yang disetujui atau menawarkan waktu pengganti.
     * Penguncian mencakup SELURUH baris aktif ruangan+tanggal (termasuk 'diajukan')
     * agar dua persetujuan bersamaan terserialisasi — tanpa ini, kunci tidak
     * menyentuh baris kandidat dan dua keputusan bisa sama-sama lolos.
     */
    private static function assertTidakKonflik(RuanganPengajuan $p): void
    {
        self::lockRowsAktif($p->ruangan_id, Carbon::parse($p->tanggal));

        $konflik = RuanganPengajuan::where('ruangan_id', $p->ruangan_id)
            ->where('id', '!=', $p->id)
            ->whereDate('tanggal', $p->tanggal)
            ->whereIn('status', [RuanganPengajuan::STATUS_DISETUJUI, RuanganPengajuan::STATUS_MENUNGGU_PENERIMAAN])
            ->where('jam_mulai', '<', $p->jam_selesai)
            ->where('jam_selesai', '>', $p->jam_mulai)
            ->exists();

        if ($konflik) {
            throw ValidationException::withMessages(['konflik' => 'Waktu bertabrakan dengan pengajuan lain yang sudah disetujui/menunggu penerimaan.']);
        }
    }

    /** Kunci semua pengajuan aktif pada ruangan+tanggal — menserialkan pengambil keputusan. */
    private static function lockRowsAktif(int $ruanganId, Carbon $tanggal): void
    {
        RuanganPengajuan::where('ruangan_id', $ruanganId)
            ->whereDate('tanggal', $tanggal)
            ->whereIn('status', [
                RuanganPengajuan::STATUS_DIAJUKAN,
                RuanganPengajuan::STATUS_MENUNGGU_PENERIMAAN,
                RuanganPengajuan::STATUS_DISETUJUI,
            ])
            ->lockForUpdate()
            ->get();
    }

    /** Izin per peserta — bukan otomatis seluruh tim. PIN per-izin untuk mahasiswa. */
    private static function terbitkanIzin(RuanganPengajuan $p): void
    {
        foreach ((array) $p->peserta as $userId) {
            $izin = RuanganIzin::firstOrCreate([
                'pengajuan_id' => $p->id,
                'user_id' => (int) $userId,
                'ruangan_id' => $p->ruangan_id,
                'mulai' => $p->mulaiAt(),
                'selesai' => $p->selesaiAt(),
            ], ['status' => RuanganIzin::STATUS_AKTIF]);

            if ($izin->wasRecentlyCreated && empty($izin->akses_pin_enc)) {
                $izin->akses_pin_enc = \Illuminate\Support\Facades\Crypt::encryptString(self::generatePinIzin());
                $izin->save();
            }
        }
    }

    /** PIN 6 digit — unik terhadap izin aktif lainnya. */
    private static function generatePinIzin(): string
    {
        for ($i = 0; $i < 25; $i++) {
            $pin = (string) random_int(100000, 999999);
            $bentrok = RuanganIzin::where('status', RuanganIzin::STATUS_AKTIF)
                ->whereNotNull('akses_pin_enc')
                ->get(['akses_pin_enc'])
                ->contains(fn (RuanganIzin $z) => $z->aksesPin() === $pin);
            if (! $bentrok) {
                return $pin;
            }
        }

        return (string) random_int(100000, 999999);
    }

    /** Cabut seluruh izin pengajuan (pembatalan/tolak/pencabutan). */
    public static function cabutIzin(RuanganPengajuan $p): void
    {
        RuanganIzin::where('pengajuan_id', $p->id)
            ->where('status', RuanganIzin::STATUS_AKTIF)
            ->update(['status' => RuanganIzin::STATUS_DICABUT]);
    }

    public static function tolak(RuanganPengajuan $p, User $penyetuju, ?string $alasan): void
    {
        DB::transaction(function () use ($p, $penyetuju, $alasan) {
            $p = RuanganPengajuan::lockForUpdate()->findOrFail($p->id);
            if (!in_array($p->status, RuanganPengajuan::STATUS_AKTIF_MENUNGGU, true)) {
                throw ValidationException::withMessages(['status' => 'Pengajuan tidak lagi menunggu keputusan.']);
            }
            $p->update([
                'status' => RuanganPengajuan::STATUS_DITOLAK,
                'diputus_oleh' => $penyetuju->id,
                'diputus_pada' => now(),
                'alasan_keputusan' => $alasan,
            ]);
            self::cabutIzin($p);
        });
        self::siarkan($p->fresh());
    }

    /** Laboran menawarkan waktu pengganti; izin baru aktif setelah pengaju menerima. */
    public static function tawarkanPengganti(RuanganPengajuan $p, User $penyetuju, Carbon $mulai, Carbon $selesai, ?string $catatan): void
    {
        DB::transaction(function () use ($p, $penyetuju, $mulai, $selesai, $catatan) {
            $p = RuanganPengajuan::lockForUpdate()->findOrFail($p->id);
            if (!in_array($p->status, RuanganPengajuan::STATUS_AKTIF_MENUNGGU, true)) {
                throw ValidationException::withMessages(['status' => 'Pengajuan tidak lagi menunggu keputusan.']);
            }
            self::assertSlotBebas($p->ruangan_id, $mulai, $selesai, $p->id);
            $p->update([
                'status' => RuanganPengajuan::STATUS_MENUNGGU_PENERIMAAN,
                'pengganti_mulai' => $mulai,
                'pengganti_selesai' => $selesai,
                'pengganti_catatan' => $catatan,
                'diputus_oleh' => $penyetuju->id,
                'diputus_pada' => now(),
            ]);
        });
        self::siarkan($p->fresh());
    }

    /** Pengaju menerima waktu pengganti — cek konflik ulang secara atomik. */
    public static function terimaPengganti(RuanganPengajuan $p, User $pengaju): void
    {
        if ((int) $p->pengaju_id !== (int) $pengaju->id) {
            abort(403);
        }
        DB::transaction(function () use ($p) {
            $p = RuanganPengajuan::lockForUpdate()->findOrFail($p->id);
            if ($p->status !== RuanganPengajuan::STATUS_MENUNGGU_PENERIMAAN || !$p->pengganti_mulai) {
                throw ValidationException::withMessages(['status' => 'Tidak ada waktu pengganti yang menunggu.']);
            }
            self::assertSlotBebas($p->ruangan_id, $p->pengganti_mulai, $p->pengganti_selesai, $p->id);
            $p->update([
                'tanggal' => $p->pengganti_mulai->toDateString(),
                'jam_mulai' => $p->pengganti_mulai->format('H:i'),
                'jam_selesai' => $p->pengganti_selesai->format('H:i'),
                'status' => RuanganPengajuan::STATUS_DISETUJUI,
                'diputus_pada' => now(),
            ]);
            self::terbitkanIzin($p->fresh());
        });
        self::siarkan($p->fresh());
    }

    public static function batalkan(RuanganPengajuan $p, User $pengaju): void
    {
        if ((int) $p->pengaju_id !== (int) $pengaju->id) {
            abort(403);
        }
        DB::transaction(function () use ($p) {
            $p = RuanganPengajuan::lockForUpdate()->findOrFail($p->id);
            if (!in_array($p->status, [RuanganPengajuan::STATUS_DIAJUKAN, RuanganPengajuan::STATUS_MENUNGGU_PENERIMAAN, RuanganPengajuan::STATUS_DISETUJUI], true)) {
                throw ValidationException::withMessages(['status' => 'Pengajuan tidak dapat dibatalkan.']);
            }
            $p->update(['status' => RuanganPengajuan::STATUS_DIBATALKAN]);
            self::cabutIzin($p);
        });
        self::siarkan($p->fresh());
    }

    /** Pencabutan oleh laboran/admin. */
    public static function cabut(RuanganPengajuan $p, User $penyetuju, ?string $alasan): void
    {
        DB::transaction(function () use ($p, $penyetuju, $alasan) {
            $p = RuanganPengajuan::lockForUpdate()->findOrFail($p->id);
            $p->update([
                'status' => RuanganPengajuan::STATUS_DIBATALKAN,
                'diputus_oleh' => $penyetuju->id,
                'diputus_pada' => now(),
                'alasan_keputusan' => $alasan,
            ]);
            self::cabutIzin($p);
        });
        self::siarkan($p->fresh());
    }

    /** Pancarkan status terbaru ke pengaju + peserta via Reverb — gagal broadcast tak menggagalkan alur. */
    private static function siarkan(RuanganPengajuan $p): void
    {
        try {
            $p->loadMissing('ruangan:id,nama');
            $penerima = collect([$p->pengaju_id, ...(array) $p->peserta])
                ->filter()->map(fn ($id) => (int) $id)->unique()->values()->all();
            if ($penerima === []) {
                return;
            }
            broadcast(new RuanganPengajuanBerubah(
                pengajuanId: (int) $p->id,
                status: (string) $p->status,
                ruangan: $p->ruangan?->nama ?? '',
                penerima: $penerima,
                deliveryId: (string) Str::uuid(),
            ));
        } catch (\Throwable $e) {
            Log::warning('Broadcast pengajuan ruangan gagal', ['id' => $p->id, 'error' => $e->getMessage()]);
        }
    }

    /** Pastikan slot bebas dari pengajuan disetujui/menunggu-penerimaan lain (di dalam transaksi+lock). */
    private static function assertSlotBebas(int $ruanganId, Carbon $mulai, Carbon $selesai, ?int $abaikanId): void
    {
        self::lockRowsAktif($ruanganId, $mulai->copy()->startOfDay());

        $konflik = RuanganPengajuan::where('ruangan_id', $ruanganId)
            ->when($abaikanId, fn ($q) => $q->where('id', '!=', $abaikanId))
            ->whereDate('tanggal', $mulai->toDateString())
            ->whereIn('status', [RuanganPengajuan::STATUS_DISETUJUI, RuanganPengajuan::STATUS_MENUNGGU_PENERIMAAN])
            ->where('jam_mulai', '<', $selesai->format('H:i'))
            ->where('jam_selesai', '>', $mulai->format('H:i'))
            ->exists();

        if ($konflik) {
            throw ValidationException::withMessages(['konflik' => 'Waktu pengganti bertabrakan dengan pengajuan lain.']);
        }
    }
}
