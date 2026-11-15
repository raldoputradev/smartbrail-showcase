<?php

namespace App\Http\Controllers\Dev;

use App\Http\Controllers\AdminController;
use App\Http\Controllers\Controller;
use App\Http\Controllers\RuanganPengajuanController;
use App\Http\Controllers\StudentPortalController;
use App\Support\PermissionRegistry;
use App\Models\AbsensiSesiSetting;
use App\Models\Announcement;
use App\Models\LogAbsensi;
use App\Models\PblProject;
use App\Models\PblTeamMember;
use App\Models\ProgramStudi;
use App\Models\Ruangan;
use App\Models\RuanganIzin;
use App\Models\RuanganPengajuan;
use App\Models\RuanganPetugas;
use App\Models\Suggestion;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

/**
 * Preview terisolasi area mahasiswa — HANYA aktif saat APP_ENV=local.
 * Merender view/komponen aplikasi yang sama (dashboard, absensi, dst.)
 * dengan database fixture SQLite terpisah di storage/app/.
 * Bukan bypass login: tidak menulis session, tidak menyentuh DB operasional.
 */
class StudentPreviewController extends Controller
{
    private const CONN = 'preview_student';

    public function handle(Request $request, string $page = 'beranda')
    {
        abort_unless(app()->isLocal(), 404);

        $this->bootFixture();

        $viewer = User::query()->where('nitk_nidn', 'PREVIEW001')->firstOrFail();
        Auth::shouldUse('web');
        Auth::setUser($viewer);
        $request->setUserResolver(fn () => $viewer);

        return match ($page) {
            'beranda' => app(StudentPortalController::class)->dashboard($request),
            'absensi' => app(StudentPortalController::class)->attendance($request),
            'kalender' => app(StudentPortalController::class)->calendarPage($request),
            'tim' => app(StudentPortalController::class)->team($request),
            'profil' => app(StudentPortalController::class)->profile($request),
            'ganti-password' => app(StudentPortalController::class)->showChangePassword($request),
            'pengajuan' => app(RuanganPengajuanController::class)->indexMahasiswa($request),
            'riwayat' => $this->riwayat($request),
            'laporan' => app(StudentPortalController::class)->suggestions($request),
            default => abort(404),
        };
    }

    private function riwayat(Request $request)
    {
        $request->query->set('tab', 'riwayat');

        return app(RuanganPengajuanController::class)->indexMahasiswa($request);
    }

    /**
     * Ketersediaan ruangan dari fixture — memakai service nyata agar
     * slot pada halaman preview identik dengan perilaku produksi.
     */
    public function ketersediaan(Request $request)
    {
        abort_unless(app()->isLocal(), 404);

        $this->bootFixture();

        $viewer = User::query()->where('nitk_nidn', 'PREVIEW001')->firstOrFail();
        Auth::shouldUse('web');
        Auth::setUser($viewer);
        $request->setUserResolver(fn () => $viewer);

        return app(RuanganPengajuanController::class)->ketersediaanMahasiswa($request);
    }

    /**
     * Preview area admin (dashboard & halaman data akun per-role) dengan
     * fixture yang sama — tidak menulis session, tidak menyentuh DB operasional.
     */
    public function admin(Request $request)
    {
        abort_unless(app()->isLocal(), 404);

        $page = (string) ($request->route('page') ?? 'dashboard');
        $role = $request->route('role');

        $this->bootFixture();

        $admin = User::query()->firstOrCreate(
            ['nitk_nidn' => 'ADM-PREVIEW'],
            [
                'nama_lengkap' => 'Admin Preview', 'role' => 'admin',
                'email' => 'admin.preview@preview.test',
                'password' => Hash::make('preview-only'),
                'is_active' => true, 'is_admin' => true,
                'approval_status' => 'approved',
            ]
        );
        Auth::shouldUse('web');
        Auth::setUser($admin);
        $request->setUserResolver(fn () => $admin);
        $request->attributes->set('dev_preview', true);

        // Riwayat Asisten AI untuk preview — dibuat sekali per fixture.
        if (\Illuminate\Support\Facades\Schema::hasTable('ai_conversations')
            && \App\Models\AiConversation::where('user_id', $admin->id)->count() === 0) {
            $mk = function (string $title, \Carbon\Carbon $lastAt, array $msgs = []) use ($admin) {
                $conv = \App\Models\AiConversation::create([
                    'user_id' => $admin->id, 'title' => $title, 'last_message_at' => $lastAt,
                ]);
                foreach ($msgs as $i => $m) {
                    \App\Models\AiMessage::create([
                        'conversation_id' => $conv->id, 'role' => $m[0], 'content' => $m[1],
                        'meta' => $m[0] === 'assistant' ? ['status' => 'complete', 'source' => 'local'] : null,
                    ]);
                }
                return $conv;
            };
            $mk('Rekap absensi minggu ini', now()->subHours(3), [
                ['user', 'Tampilkan rekap absensi minggu ini'],
                ['assistant', "Ringkasan absensi minggu ini:\n- Senin–Jumat: 12 mahasiswa aktif\n- Rata-rata hadir 92%\n- Tidak lengkap: 2 kali tap\n\nMau saya tampilkan grafiknya juga?"],
            ]);
            $mk('Cek status mesin IoT', now()->subDays(4), [
                ['user', 'Cek status mesin IoT saat ini'],
                ['assistant', 'Armada preview: 1 mesin online, sisanya offline/nonaktif. Detail di menu Monitoring Mesin.'],
            ]);
            $mk('Daftar pengajuan ruangan', now()->subDays(9), [
                ['user', 'Tampilkan pengajuan ruangan yang menunggu'],
                ['assistant', 'Ada 2 pengajuan menunggu keputusan — buka menu Pengajuan Ruangan untuk meninjau.'],
            ]);
        }

        // Armada mesin fixture — dibuat sekali per database preview; mencakup
        // semua status (online/offline/nonaktif) + cukup banyak untuk pagination.
        if (\Illuminate\Support\Facades\Schema::hasTable('mesin_iot')
            && \App\Models\MesinIot::count() === 0) {
            $room = \App\Models\Ruangan::query()->where('kode', 'R-MTG')->first();
            $mkMesin = function (string $id, string $nama, string $tipe, bool $aktif, ?\Carbon\Carbon $seen, ?int $roomId) {
                \App\Models\MesinIot::create([
                    'id_mesin' => $id, 'nama_mesin' => $nama, 'tipe' => $tipe,
                    'ruangan_id' => $roomId, 'api_key' => Hash::make('preview-key-' . $id),
                    'is_active' => $aktif, 'last_seen_at' => $seen,
                ]);
            };
            $mkMesin('MESIN-PREVIEW', 'Mesin Absensi Lobby', 'absensi', true, now()->subMinutes(2), $room?->id);
            $mkMesin('M-RUA-001', 'Akses Pintu R-MTG', 'ruangan', true, now()->subMinutes(4), $room?->id);
            $mkMesin('M-ABS-002', 'Mesin Absensi Lt.2', 'absensi', true, now()->subHours(3), null);
            $mkMesin('M-RUA-002', 'Akses Pintu R-MEK', 'ruangan', true, now()->subDays(1)->subHours(2), null);
            $mkMesin('M-ABS-003', 'Mesin Absensi Cadangan', 'absensi', false, now()->subDays(6), null);
            for ($i = 10; $i <= 22; $i++) {
                $mkMesin(
                    'M-ABS-0' . $i, 'Mesin Absensi ' . $i, 'absensi',
                    $i % 4 !== 0,
                    $i % 4 === 0 ? now()->subDays($i) : now()->subHours($i),
                    null
                );
            }

            // Tap hari ini agar grafik jam + feed aktivitas monitoring terisi.
            if (LogAbsensi::query()->whereDate('waktu_tap', today())->count() === 0) {
                $viewer = User::query()->where('nitk_nidn', 'PREVIEW001')->first();
                if ($viewer) {
                    $hours = array_values(array_filter([7, 8, 8, 12, 16], fn ($h) => $h < (int) now()->format('H')));
                    foreach (count($hours) ? $hours : [7] as $h) {
                        LogAbsensi::create([
                            'user_id' => $viewer->id, 'id_mesin' => 'MESIN-PREVIEW',
                            'sesi' => 'pagi', 'jenis' => $h < 12 ? 'masuk' : 'pulang',
                            'event_id' => (string) \Illuminate\Support\Str::uuid(),
                            'waktu_tap' => today()->setTime($h, 15),
                            'diterima_pada' => today()->setTime($h, 15),
                        ]);
                    }
                }
            }
        }

        // Fixture Log Tap — tap beragam pengguna/mesin/arah agar halaman
        // Log Akses Operasional punya data nyata (filter, rincian, pagination).
        if (LogAbsensi::query()->whereDate('waktu_tap', today())->count() < 15) {
            $tapUsers = User::query()->where('role', 'mahasiswa')->inRandomOrder()->limit(12)->get();
            $tapMesin = ['MESIN-PREVIEW', 'M-ABS-002'];
            foreach ($tapUsers as $ui => $tu) {
                // masuk pagi
                $late = $ui % 5 === 0;
                LogAbsensi::create([
                    'user_id' => $tu->id, 'id_mesin' => $tapMesin[$ui % 2],
                    'sesi' => 'pagi', 'jenis' => 'masuk',
                    'menit_terlambat' => $late ? 7 + $ui : 0,
                    'event_id' => (string) \Illuminate\Support\Str::uuid(),
                    'waktu_tap' => today()->setTime(7, 20 + $ui * 2),
                    'diterima_pada' => today()->setTime(7, 20 + $ui * 2),
                ]);
                // sebagian tap pulang
                if ($ui % 3 !== 0) {
                    LogAbsensi::create([
                        'user_id' => $tu->id, 'id_mesin' => $tapMesin[$ui % 2],
                        'sesi' => 'pagi', 'jenis' => 'pulang',
                        'menit_pulang_cepat' => $ui % 7 === 0 ? 12 : 0,
                        'pulang_cepat' => $ui % 7 === 0,
                        'event_id' => (string) \Illuminate\Support\Str::uuid(),
                        'waktu_tap' => today()->setTime(16, 40 + $ui),
                        'diterima_pada' => today()->setTime(16, 40 + $ui),
                    ]);
                }
                // tap kemarin agar rentang tanggal teruji
                LogAbsensi::create([
                    'user_id' => $tu->id, 'id_mesin' => $tapMesin[($ui + 1) % 2],
                    'sesi' => 'pagi', 'jenis' => 'masuk',
                    'event_id' => (string) \Illuminate\Support\Str::uuid(),
                    'waktu_tap' => today()->subDay()->setTime(7, 30 + $ui),
                    'diterima_pada' => today()->subDay()->setTime(7, 30 + $ui),
                ]);
            }
        }

        // Fixture pengaturan absensi — sesi pagi/malam + kalender akademik.
        if (\Illuminate\Support\Facades\Schema::hasTable('absensi_sesi_settings')
            && \App\Models\AbsensiSesiSetting::count() === 0) {
            \App\Models\AbsensiSesiSetting::create([
                'shift' => 'pagi', 'jam_masuk' => '07:50', 'jam_mulai_tap_masuk' => '06:30',
                'jam_pulang' => '17:00', 'toleransi_masuk_menit' => 15,
                'toleransi_pulang_menit' => 15, 'grace_alfa_menit' => 60,
                'aktif' => true, 'timezone' => 'Asia/Jakarta',
            ]);
            \App\Models\AbsensiSesiSetting::create([
                'shift' => 'malam', 'jam_masuk' => '18:30',
                'jam_pulang' => '21:30', 'toleransi_masuk_menit' => 15,
                'toleransi_pulang_menit' => 15, 'grace_alfa_menit' => 60,
                'aktif' => false, 'timezone' => 'Asia/Jakarta',
            ]);
        }
        if (\Illuminate\Support\Facades\Schema::hasTable('absensi_kalender_libur')
            && \App\Models\AbsensiKalenderLibur::count() === 0) {
            \App\Models\AbsensiKalenderLibur::create([
                'tanggal' => today()->addDays(6)->toDateString(),
                'tanggal_selesai' => today()->addDays(6)->toDateString(),
                'jenis' => 'libur', 'dampak_absensi' => 'batal_wajib',
                'keterangan' => 'Hari Libur Nasional (preview)',
            ]);
            \App\Models\AbsensiKalenderLibur::create([
                'tanggal' => today()->addDays(20)->toDateString(),
                'tanggal_selesai' => today()->addDays(24)->toDateString(),
                'jenis' => 'ujian', 'dampak_absensi' => 'tetap_wajib',
                'keterangan' => 'Periode Ujian Tengah Semester (preview)',
            ]);
            \App\Models\AbsensiKalenderLibur::create([
                'tanggal' => today()->addDays(12)->toDateString(),
                'tanggal_selesai' => today()->addDays(12)->toDateString(),
                'jenis' => 'perubahan_jam', 'dampak_absensi' => 'ubah_jam',
                'jam_masuk_khusus' => '09:00', 'jam_pulang_khusus' => '14:00',
                'keterangan' => 'Jam khusus rapat dosen (preview)',
            ]);
            \App\Models\AbsensiKalenderLibur::create([
                'tanggal' => today()->addDays(3)->toDateString(),
                'tanggal_selesai' => today()->addDays(3)->toDateString(),
                'jenis' => 'peringatan', 'dampak_absensi' => 'tetap_wajib',
                'keterangan' => 'Peringatan Hari Pancasila (preview)',
            ]);
            \App\Models\AbsensiKalenderLibur::create([
                'tanggal' => today()->addDays(9)->toDateString(),
                'tanggal_selesai' => today()->addDays(9)->toDateString(),
                'jenis' => 'agenda', 'dampak_absensi' => 'tetap_wajib',
                'keterangan' => 'Praktikum IoT Kelas Pagi (preview)',
            ]);
            \App\Models\AbsensiKalenderLibur::create([
                'tanggal' => today()->addDays(16)->toDateString(),
                'tanggal_selesai' => today()->addDays(16)->toDateString(),
                'jenis' => 'informasi', 'dampak_absensi' => 'tetap_wajib',
                'keterangan' => 'Pengumuman perawatan mesin (preview)',
            ]);
        }

        // Fixture fingerprint — sebagian mahasiswa enrolled/pending/legacy,
        // sebagian belum assign; + log sync template antarmesin.
        if (\Illuminate\Support\Facades\Schema::hasTable('users')
            && \App\Models\User::where('role', 'mahasiswa')->whereNotNull('finger_id')->count() < 6) {
            $mkTpl = fn () => base64_encode(random_bytes(\App\Support\FingerprintTemplateCodec::TEMPLATE_BYTE_LENGTH));
            $usedIds = \App\Models\User::whereNotNull('finger_id')->pluck('finger_id')->all();
            $mhs = \App\Models\User::where('role', 'mahasiswa')->whereNull('finger_id')->orderBy('id')->get();
            $alloc = function () use (&$usedIds) { $n = 1; while (in_array($n, $usedIds)) $n++; $usedIds[] = $n; return $n; };
            // 5 enrolled (template valid, updated_at bervariasi → tren 7 hari)
            foreach ($mhs->take(5)->values() as $i => $u) {
                $u->timestamps = false;
                $u->finger_id = $alloc();
                $u->finger_template = $mkTpl();
                $u->shift = $u->shift ?: 'pagi';
                $u->save();
                \App\Models\User::where('id', $u->id)->update(['updated_at' => now()->subDays(max(0, 5 - $i))->subHours(3)]);
            }
            // 2 pending (punya slot, belum scan)
            foreach ($mhs->slice(5, 2)->values() as $u) {
                \App\Models\User::where('id', $u->id)->update(['finger_id' => $alloc()]);
            }
            // 1 legacy (placeholder lama → perlu enroll ulang)
            if ($legacy = $mhs->get(7)) {
                $legacy->finger_id = $alloc();
                $legacy->finger_template = 'SENSOR_ENROLLED';
                $legacy->timestamps = false;
                $legacy->save();
            }
            // Sisanya: unassigned (tanpa finger_id)
        }
        if (\Illuminate\Support\Facades\Schema::hasTable('fingerprint_sync_logs')
            && \App\Models\FingerprintSyncLog::count() === 0) {
            $mkLog = function (string $act, int $n, int $daysAgo, bool $ok = true) {
                \App\Models\FingerprintSyncLog::create([
                    'id_mesin' => 'MESIN-PREVIEW', 'mesin_tipe' => 'absensi', 'action' => $act,
                    'since' => $daysAgo, 'after_finger_id' => $daysAgo + $n,
                    'batch_limit' => 50, 'templates_count' => $n,
                    'finger_ids' => $daysAgo . '-' . ($daysAgo + $n),
                    'sync_token' => 'preview', 'success' => $ok,
                    'message' => $ok ? 'OK' : 'Checksum mismatch',
                    'ip_address' => '10.10.10.5',
                    'requested_at' => now()->subDays($daysAgo)->subHours(2),
                ]);
            };
            $mkLog('sync_pull', 12, 0);
            $mkLog('sync_pull', 8, 1);
            $mkLog('sync_pull', 20, 3);
            $mkLog('sync_pull', 5, 5, false);
            $mkLog('enroll_upload', 1, 2);
        }

        // Bulk mahasiswa + log approval agar daftar pengguna punya data
        // cukup untuk pagination, komposisi donut, dan feed aktivitas.
        if (\Illuminate\Support\Facades\Schema::hasTable('users')
            && \App\Models\User::count() < 40) {
            $prodi = \App\Models\ProgramStudi::first() ?? \App\Models\ProgramStudi::create(['kode' => 'RBT', 'nama' => 'Teknik Robotika', 'aktif' => true]);
            $first = ['Bima','Citra','Dewi','Eko','Fajar','Gita','Hadi','Intan','Joko','Kirana','Lukman','Maya','Nanda','Oscar','Putri','Rizki','Sari','Taufik','Umar','Vina','Wahyu','Xaverius','Yoga','Zahra','Andre','Bella','Candra','Dina','Edo','Farah','Galih','Hana','Irfan','Jihan','Kevin','Lia','Made','Nita','Oki','Prita','Raka','Sinta','Teguh','Udin','Vera','Wulan','Yusuf','Zaki'];
            $last = ['Pratama','Santuri','Nabilah','Sinaga','Wulandari','Firmansyah','Ramadhan','Kusuma','Anggraini','Saputra'];
            $kelasPool = ['Robotika A','Robotika B','Robotika C','Mekatronika A','Rekayasa Elektro B'];
            $timPool = ['Tim Alpha','Tim Beta','SmartBrail','IoT Lab','Rover'];
            $n = 0;
            foreach ($first as $f) {
                if (\App\Models\User::count() >= 62) break;
                $i = $n++;
                \App\Models\User::create([
                    'nama_lengkap' => "$f {$last[$i % 10]}",
                    'nitk_nidn' => '422' . str_pad((string) (2401000 + $i), 7, '0', STR_PAD_LEFT),
                    'email' => strtolower($f) . ($i % 9 === 0 ? '' : $i) . '@preview.test',
                    'password' => \Illuminate\Support\Facades\Hash::make('preview-only'),
                    'role' => 'mahasiswa',
                    'is_active' => $i % 11 !== 0,
                    'approval_status' => $i % 13 === 0 ? 'pending' : 'approved',
                    'email_verified_at' => $i % 7 === 0 ? null : now()->subDays(20 + $i),
                    'kelas' => $kelasPool[$i % 5],
                    'tim' => $timPool[$i % 5],
                    'angkatan' => 2022 + ($i % 4),
                    'semester' => 3 + ($i % 6),
                    'semester_type' => $i % 2 === 0 ? 'ganjil' : 'genap',
                    'program_studi_id' => $prodi->id,
                    'shift' => $i % 3 === 0 ? 'malam' : 'pagi',
                    'created_at' => now()->subDays(2 + ($i % 60)),
                ]);
            }
            $dosenExtra = [['Sari Wulandari, M.Kom.', 'D00002'], ['Joko Prasetyo, M.T.', 'D00003']];
            foreach ($dosenExtra as [$dn, $dnitk]) {
                if (\App\Models\User::where('nitk_nidn', $dnitk)->exists()) continue;
                $d = \App\Models\User::create([
                    'nama_lengkap' => $dn, 'nitk_nidn' => $dnitk,
                    'email' => strtolower(explode(' ', $dn)[0]) . '@preview.test',
                    'password' => \Illuminate\Support\Facades\Hash::make('preview-only'),
                    'role' => 'dosen', 'is_active' => true,
                    'approval_status' => 'approved', 'email_verified_at' => now()->subDays(90),
                ]);
                \App\Models\DosenKelas::firstOrCreate(['user_id' => $d->id, 'kelas' => 'Robotika A']);
            }
        }
        if (\Illuminate\Support\Facades\Schema::hasTable('account_approval_logs')
            && \App\Models\AccountApprovalLog::count() < 8) {
            $admin = \App\Models\User::where('nitk_nidn', 'ADM-PREVIEW')->first();
            $mkAlog = function (string $act, ?\App\Models\User $u, ?\Carbon\Carbon $at, ?string $note) use ($admin) {
                $log = new \App\Models\AccountApprovalLog([
                    'user_id' => $u?->id,
                    'subject_name' => $u?->nama_lengkap ?? 'Pengguna sistem',
                    'subject_email' => $u?->email,
                    'subject_nitk_nidn' => $u?->nitk_nidn,
                    'subject_role' => $u?->role,
                    'actor_id' => $admin?->id,
                    'action' => $act,
                    'note' => $note,
                    'ip_address' => '127.0.0.1',
                ]);
                $log->created_at = $at;
                $log->updated_at = $at;
                $log->save();
            };
            $someMhs = \App\Models\User::where('role', 'mahasiswa')->orderBy('id')->limit(6)->get();
            $mkAlog('admin_created', $someMhs->get(0), now()->subMinutes(10), 'User dibuat manual oleh admin.');
            $mkAlog('approved', $someMhs->get(0), now()->subMinutes(9), 'User langsung diaktifkan saat dibuat manual oleh admin.');
            $mkAlog('admin_created', $someMhs->get(1), now()->subHours(3), 'User dibuat manual oleh admin.');
            $mkAlog('email_verified', $someMhs->get(2), now()->subHours(7), null);
            $mkAlog('deactivated', $someMhs->get(3), now()->subDay(), 'Akun dinonaktifkan sementara.');
            $mkAlog('activated', $someMhs->get(3), now()->subHours(20), 'Akun diaktifkan kembali.');
            $mkAlog('admin_created', $someMhs->get(4), now()->subDays(2), 'User dibuat manual oleh admin.');
            $mkAlog('activation_code_issued', $someMhs->get(5), now()->subDays(3), 'Kode aktivasi dibuat oleh admin.');
        }

        // Samarkan nama route agar penanda aktif menu (routeIs) bekerja seperti
        // halaman nyata. ?as_route=<name> untuk mensimulasikan halaman lain.
        $routeName = $request->query('as_route') ?: match ($page) {
            'dashboard' => 'admin.dashboard',
            'users' => $role !== null ? 'admin.users.role' : 'admin.users',
            'ai' => 'admin.ai',
            'mesin' => 'admin.mesin.index',
            'monitoring' => 'admin.monitoring',
            'log-tap' => 'admin.log.absensi',
            'atur-absensi' => 'admin.absensi.settings',
            'fingerprint' => 'admin.fingerprint',
            'ruangan' => 'admin.ruangan.index',
            'pengajuan' => 'admin.pengajuan.index',
            'petugas' => 'admin.petugas.index',
            'pengumuman' => 'admin.announcements.index',
            'saran' => 'admin.suggestions.index',
            'antrian' => 'admin.notifications',
            'pengaturan' => 'admin.settings',
            'kalender' => 'admin.kalender',
            default => null,
        };
        if ($routeName && ($r = \Illuminate\Support\Facades\Route::getRoutes()->getByName($routeName))) {
            $r->bind($request);
            if ($role !== null) {
                $r->setParameter('role', $role);
            }
            $request->setRouteResolver(fn () => $r);
        }

        return match ($page) {
            'dashboard' => app(AdminController::class)->dashboard($request),
            'users' => $role !== null
                ? app(AdminController::class)->usersRole($request, $role)
                : app(AdminController::class)->users($request),
            'ai' => app(\App\Http\Controllers\Admin\AdminAiController::class)->index($request),
            'mesin' => app(\App\Http\Controllers\MesinIotController::class)->index($request),
            'monitoring' => app(AdminController::class)->monitoring($request),
            'log-tap' => app(\App\Http\Controllers\AdminLogController::class)->absensi($request),
            'atur-absensi' => app(\App\Http\Controllers\AbsensiManagementController::class)->settings($request),
            'fingerprint' => app(AdminController::class)->fingerprint($request),
            'ruangan' => app(\App\Http\Controllers\AdminRuanganController::class)->index($request),
            'pengajuan' => app(\App\Http\Controllers\RuanganPengajuanController::class)->indexLaboran($request),
            'petugas' => app(\App\Http\Controllers\AdminRuanganController::class)->petugasIndex($request),
            'pengumuman' => app(\App\Http\Controllers\AnnouncementController::class)->index($request),
            'saran' => app(\App\Http\Controllers\Admin\SuggestionController::class)->index($request),
            'antrian' => app(AdminController::class)->notifications($request),
            'pengaturan' => app(AdminController::class)->settings($request),
            'kalender' => app(\App\Http\Controllers\AbsensiManagementController::class)->kalender($request),
            default => abort(404),
        };
    }

    /** Data grafik dashboard dari fixture — endpoint nyata butuh session login. */
    public function adminDashboardData(Request $request)
    {
        abort_unless(app()->isLocal(), 404);

        $this->bootFixture();

        $admin = User::query()->where('nitk_nidn', 'ADM-PREVIEW')->firstOrFail();
        Auth::shouldUse('web');
        Auth::setUser($admin);
        $request->setUserResolver(fn () => $admin);

        return app(AdminController::class)->dashboardData($request);
    }

    /** Feed monitoring dari fixture — endpoint nyata butuh session login. */
    public function adminMonitoringFeed(Request $request)
    {
        abort_unless(app()->isLocal(), 404);

        $this->bootFixture();

        $admin = User::query()->where('nitk_nidn', 'ADM-PREVIEW')->firstOrFail();
        Auth::shouldUse('web');
        Auth::setUser($admin);
        $request->setUserResolver(fn () => $admin);

        return app(AdminController::class)->monitoringFeed($request);
    }

    /** Detail percakapan AI dari fixture — endpoint nyata butuh session login. */
    public function adminAiConversation(Request $request, $conversation)
    {
        abort_unless(app()->isLocal(), 404);

        $this->bootFixture();

        $admin = User::query()->where('nitk_nidn', 'ADM-PREVIEW')->firstOrFail();
        Auth::shouldUse('web');
        Auth::setUser($admin);
        $request->setUserResolver(fn () => $admin);

        $conv = \App\Models\AiConversation::where('user_id', $admin->id)->findOrFail($conversation);

        return app(\App\Http\Controllers\Admin\AdminAiController::class)->showConversation($request, $conv);
    }

    private function bootFixture(): void
    {
        $path = storage_path('app/student-preview.sqlite');
        $fresh = ! is_file($path);
        if ($fresh) {
            touch($path);
        }

        config(['database.connections.' . self::CONN => [
            'driver' => 'sqlite',
            'database' => $path,
            'foreign_key_constraints' => true,
        ]]);
        DB::setDefaultConnection(self::CONN);

        if ($fresh || ! Schema::hasTable('users') || User::count() === 0) {
            Artisan::call('migrate', ['--database' => self::CONN, '--force' => true]);
            $this->seed();
        }

        PermissionRegistry::seedDefaults();
    }

    private function seed(): void
    {
        $tz = 'Asia/Jakarta';
        $now = Carbon::now($tz);

        $prodi = ProgramStudi::create(['kode' => 'RBT', 'nama' => 'Teknik Robotika', 'aktif' => true]);

        $mkUser = fn (array $attrs) => User::create(array_merge([
            'email' => strtolower(str_replace(' ', '.', $attrs['nama_lengkap'] ?? 'user')) . '@preview.test',
            'no_hp' => null,
            'password' => Hash::make('preview-only'),
            'must_change_password' => false, 'is_active' => true,
            'approval_status' => 'approved', 'kelas' => 'Robotika C',
            'shift' => 'pagi', 'angkatan' => '2023', 'semester' => '6',
        ], $attrs));

        $viewer = $mkUser([
            'nama_lengkap' => 'Rayendra Putra', 'nitk_nidn' => 'PREVIEW001',
            'role' => 'mahasiswa', 'program_studi_id' => $prodi->id,
            'email' => 'rayendra@example.test', 'email_verified_at' => $now,
            'no_hp' => '0812 3456 7890', 'finger_id' => 7,
            'finger_template' => \App\Support\FingerprintTemplateCodec::encodeForStorage(random_bytes(512)),
        ]);

        $names = [
            'Aldo Pratama', 'Alicia Santuri', 'Alya Nabilah', 'Aprilia Sinaga',
            'Nabila Ichmawati', 'Naira Fayyaza', 'Nanik Purwaningrum', 'Siti Robbiah',
            'Tini Wulandari', 'Viona Syafitri', 'Yuyun Rahmawati',
        ];
        $members = collect($names)->map(fn ($n, $i) => $mkUser([
            'nama_lengkap' => $n, 'nitk_nidn' => 'PV' . str_pad((string) ($i + 2), 5, '0', STR_PAD_LEFT),
            'role' => 'mahasiswa', 'program_studi_id' => $prodi->id,
        ]));

        $dosen = $mkUser(['nama_lengkap' => 'Bima Santosa, M.T.', 'nitk_nidn' => 'D00001', 'role' => 'dosen']);
        $laboran = $mkUser(['nama_lengkap' => 'Petugas Laboran', 'nitk_nidn' => 'L00001', 'role' => 'laboran']);

        $project = PblProject::create([
            'kode' => 'PBL-RE-044', 'nama' => 'SmartBrail — Sistem Absensi Cerdas',
            'manpro' => $dosen->nama_lengkap, 'co_manpro' => null,
            'shift_sesi' => 'pagi', 'manpro_user_id' => $dosen->id, 'aktif' => true,
        ]);

        PblTeamMember::create(['pbl_project_id' => $project->id, 'user_id' => $members[0]->id, 'role' => PblTeamMember::ROLE_KETUA]);
        PblTeamMember::create(['pbl_project_id' => $project->id, 'user_id' => $viewer->id, 'role' => PblTeamMember::ROLE_ANGGOTA]);
        foreach ($members->skip(1) as $m) {
            PblTeamMember::create(['pbl_project_id' => $project->id, 'user_id' => $m->id, 'role' => PblTeamMember::ROLE_ANGGOTA]);
        }

        // Ruangan dengan aturan nyata + profil (kolom migrasi 2026_11_12_130000).
        $meeting = Ruangan::create([
            'kode' => 'R-MTG', 'nama' => 'Ruang Meeting — BRAIL',
            'lokasi' => 'Gedung BRAIL Lt. 2', 'aktif' => true,
            'kapasitas' => 12, 'fasilitas' => ['Proyektor', 'TV', 'AC', 'Wi-Fi'],
            'aturan' => [
                'hari' => [1, 2, 3, 4, 5], 'jam_buka' => '07:00', 'jam_tutup' => '17:00',
                'max_advance_days' => 2, 'max_duration_minutes' => 300,
                'durasi_opsi' => [60, 120, 180, 240], 'max_peserta' => 12,
                'max_pending_per_team' => 2,
            ],
        ]);
        Ruangan::create([
            'kode' => 'R-MEK', 'nama' => 'Ruang Mekanikal — BRAIL',
            'lokasi' => 'Gedung BRAIL Lt. 1', 'aktif' => true,
            'kapasitas' => 6, 'fasilitas' => ['Peralatan praktikum', 'Meja kerja'],
            'aturan' => [
                'hari' => [1, 2], 'jam_buka' => '08:00', 'jam_tutup' => '16:00',
                'max_advance_days' => 3, 'max_duration_minutes' => 120,
                'durasi_opsi' => [60, 120], 'max_peserta' => 6,
                'max_pending_per_team' => 1,
            ],
        ]);

        // Jadwal petugas (cleaning) memblokir sebagian slot.
        RuanganPetugas::create([
            'nama' => 'Petugas Kebersihan', 'id_internal' => 'CS-01',
            'tipe_kredensial' => 'pin', 'kredensial_hash' => 'preview',
            'status' => 'aktif', 'berlaku_mulai' => $now->copy()->subDays(90)->toDateString(),
            'jadwal' => [[
                'ruangan_id' => $meeting->id, 'hari' => [1, 3, 5],
                'jam_mulai' => '16:00', 'jam_selesai' => '17:00',
            ]],
        ]);

        // Pengajuan beragam status untuk riwayat + kartu "berikutnya".
        $peserta3 = [$viewer->id, $members[0]->id, $members[1]->id];
        $mkPengajuan = function (array $attrs) use ($meeting, $viewer, $project, $dosen, $laboran, $peserta3, $now) {
            return RuanganPengajuan::create(array_merge([
                'ruangan_id' => $meeting->id, 'pengaju_id' => $viewer->id,
                'pbl_project_id' => $project->id, 'pembimbing_id' => $dosen->id,
                'tujuan' => 'Presentasi perkembangan proyek PBL SmartBrail',
                'peserta' => $peserta3,
                'snapshot' => ['peserta_nama' => ['Rayendra Putra', 'Aldo Pratama', 'Alicia Santuri']],
                'diputus_oleh' => $laboran->id, 'diputus_pada' => $now->copy()->subDays(2),
            ], $attrs));
        };

        $nextDay = $now->copy()->addDay();
        while ($nextDay->isWeekend()) {
            $nextDay->addDay();
        }

        $approvedNext = $mkPengajuan([
            'status' => 'disetujui', 'tanggal' => $nextDay->toDateString(),
            'jam_mulai' => '09:00', 'jam_selesai' => '11:00',
            'tujuan' => 'Sinkronisasi modul fingerprint tim',
        ]);
        RuanganIzin::create([
            'pengajuan_id' => $approvedNext->id, 'user_id' => $viewer->id,
            'ruangan_id' => $meeting->id,
            'mulai' => $nextDay->copy()->setTime(9, 0),
            'selesai' => $nextDay->copy()->setTime(11, 0),
            'status' => RuanganIzin::STATUS_AKTIF,
        ]);

        $mkPengajuan(['status' => 'diajukan', 'tanggal' => $nextDay->copy()->addDay()->toDateString(), 'jam_mulai' => '13:00', 'jam_selesai' => '15:00', 'diputus_oleh' => null, 'diputus_pada' => null]);
        $mkPengajuan([
            'status' => 'menunggu_penerimaan', 'tanggal' => $nextDay->toDateString(),
            'jam_mulai' => '08:00', 'jam_selesai' => '10:00',
            'pengganti_mulai' => $nextDay->copy()->setTime(13, 0),
            'pengganti_selesai' => $nextDay->copy()->setTime(15, 0),
            'pengganti_catatan' => 'Slot pagi sudah terpakai kegiatan lain.',
        ]);
        $mkPengajuan([
            'status' => 'ditolak', 'tanggal' => $now->copy()->subDays(1)->toDateString(),
            'jam_mulai' => '09:00', 'jam_selesai' => '11:00',
            'alasan_keputusan' => 'Bentrok dengan jadwal perawatan ruangan.',
            'diputus_pada' => $now->copy()->subDays(1),
        ]);
        $mkPengajuan([
            'status' => 'selesai', 'tanggal' => $now->copy()->subDays(5)->toDateString(),
            'jam_mulai' => '09:00', 'jam_selesai' => '11:00',
            'tujuan' => 'Review tahap 2 bersama pembimbing',
        ]);
        $mkPengajuan([
            'status' => 'dibatalkan', 'tanggal' => $now->copy()->subDays(3)->toDateString(),
            'jam_mulai' => '14:00', 'jam_selesai' => '16:00',
        ]);

        // Sesi & kebijakan absensi (jadwal mingguan Senin–Jumat 07:50–17:00).
        AbsensiSesiSetting::updateOrCreate(['shift' => 'pagi'], [
            'jam_masuk' => '07:50', 'jam_pulang' => '17:00',
            'jam_mulai_tap_masuk' => '06:30', 'toleransi_masuk_menit' => 15,
            'toleransi_pulang_menit' => 15, 'grace_alfa_menit' => 120,
            'aktif' => true, 'timezone' => $tz,
        ]);
        $weekly = [];
        for ($d = 0; $d <= 6; $d++) {
            $aktif = $d >= 1 && $d <= 5;
            $weekly[(string) $d] = [
                'aktif' => $aktif,
                'jam_masuk' => $aktif ? '07:50' : null,
                'jam_pulang' => $aktif ? '17:00' : null,
                'jam_mulai_tap_masuk' => $aktif ? '06:30' : null,
            ];
        }
        DB::table('system_settings')->updateOrInsert(
            ['key' => 'absensi_kebijakan'],
            [
            'value' => json_encode([
                'mode' => 'jadwal_mingguan',
                'effective_from' => $now->copy()->subMonths(3)->toDateString(),
                'legacy_mode' => 'kamis', 'legacy_hari_wajib' => [4],
                'jadwal_mingguan' => $weekly,
            ]),
            'created_at' => $now, 'updated_at' => $now,
            ]
        );

        // Log absensi ~5 minggu terakhir: lengkap + beberapa tidak lengkap/terlambat.
        for ($i = 1; $i <= 35; $i++) {
            $day = $now->copy()->subDays($i);
            if ($day->isWeekend()) {
                continue;
            }
            $late = $i % 9 === 0;
            $onlyIn = $i % 11 === 0;
            if ($i % 13 === 0) {
                continue; // alfa
            }
            LogAbsensi::create([
                'user_id' => $viewer->id, 'id_mesin' => 'MESIN-PREVIEW',
                'sesi' => 'pagi', 'jenis' => 'masuk', 'event_id' => (string) \Illuminate\Support\Str::uuid(),
                'menit_terlambat' => $late ? 22 : 0,
                'waktu_tap' => $day->copy()->setTime(7, $late ? 52 : 42),
                'diterima_pada' => $day->copy()->setTime(7, $late ? 52 : 42),
            ]);
            if (! $onlyIn) {
                LogAbsensi::create([
                    'user_id' => $viewer->id, 'id_mesin' => 'MESIN-PREVIEW',
                    'sesi' => 'pagi', 'jenis' => 'pulang', 'event_id' => (string) \Illuminate\Support\Str::uuid(),
                    'waktu_tap' => $day->copy()->setTime(17, 5),
                    'diterima_pada' => $day->copy()->setTime(17, 5),
                ]);
            }
        }

        // Riwayat laporan/saran milik viewer — beragam kategori & status.
        $mkLaporan = function (array $attrs) use ($viewer) {
            $createdAt = $attrs['created_at'] ?? null;
            $updatedAt = $attrs['updated_at'] ?? null;
            unset($attrs['created_at'], $attrs['updated_at']);
            $s = Suggestion::create(array_merge([
                'user_id' => $viewer->id, 'source' => 'mahasiswa',
                'nama' => $viewer->nama_lengkap, 'email' => $viewer->email,
            ], $attrs));
            if ($createdAt || $updatedAt) {
                $s->timestamps = false;
                $s->created_at = $createdAt ?? $s->created_at;
                $s->updated_at = $updatedAt ?? $s->updated_at;
                $s->save();
            }

            return $s;
        };
        $mkLaporan([
            'subjek' => '[Kendala Absensi ' . $now->copy()->subDays(1)->toDateString() . '] Rekaman tap pulang tidak tercatat',
            'pesan' => "Saya sudah tap pulang di mesin lobby pukul 17:05, tetapi di riwayat absensi status masih \"hanya masuk\".\nMohon dibantu koreksi rekamannya.",
            'status' => 'ditanggapi',
            'admin_reply' => 'Sudah kami koreksi di sistem — rekaman tap pulang Anda kini tercatat lengkap. Silakan periksa kembali halaman Absensi.',
            'admin_replied_at' => $now->copy()->subHours(20), 'admin_replied_by' => $laboran->id,
            'user_read_reply_at' => $now->copy()->subHours(5),
            'created_at' => $now->copy()->subDays(2), 'updated_at' => $now->copy()->subHours(5),
        ]);
        $mkLaporan([
            'subjek' => '[Kendala Absensi ' . $now->copy()->subDays(3)->toDateString() . '] Terlambat karena antrean mesin',
            'pesan' => 'Pagi itu antrean tap masuk panjang sehingga tercatat terlambat 4 menit. Mohon pertimbangannya.',
            'status' => 'dibaca',
            'created_at' => $now->copy()->subDays(3)->subHours(2), 'updated_at' => $now->copy()->subDays(3),
        ]);
        $mkLaporan([
            'subjek' => '[Akun] Tidak bisa masuk dengan NITK baru',
            'pesan' => 'Setelah pergantian kartu, login dengan NITK baru selalu gagal. Akun lama masih bisa dipakai.',
            'status' => 'baru',
            'created_at' => $now->copy()->subHours(6), 'updated_at' => $now->copy()->subHours(6),
        ]);
        $mkLaporan([
            'subjek' => 'Usul penambahan jam booking malam untuk tim shift sore',
            'pesan' => 'Tim shift sore sering kehabisan slot. Usul jam operasional ruang meeting diperpanjang sampai 19:00.',
            'status' => 'ditanggapi',
            'admin_reply' => 'Masukan bagus — sedang kami kaji bersama pengelola gedung. Akan diumumkan bila disetujui.',
            'admin_replied_at' => $now->copy()->subDays(1)->subHours(3), 'admin_replied_by' => $laboran->id,
            'created_at' => $now->copy()->subDays(4), 'updated_at' => $now->copy()->subDays(1)->subHours(3),
        ]);
        $mkLaporan([
            'subjek' => 'AC ruang praktikum terasa kurang dingin',
            'pesan' => 'Sudah dua hari AC ruang mekanikal tidak dingin, mengganggu kegiatan praktikum siang.',
            'status' => 'baru',
            'created_at' => $now->copy()->subDays(5), 'updated_at' => $now->copy()->subDays(5),
        ]);
        $mkLaporan([
            'subjek' => '[Kendala Absensi ' . $now->copy()->subDays(8)->toDateString() . '] Fingerprint sering gagal terbaca',
            'pesan' => 'Sidik jari saya sering ditolak mesin, perlu 4-5 kali coba. Apakah perlu pendaftaran ulang?',
            'status' => 'ditanggapi',
            'admin_reply' => 'Silakan datang ke ruang laboran untuk pendaftaran ulang sidik jari, bawa kartu mahasiswa.',
            'admin_replied_at' => $now->copy()->subDays(7), 'admin_replied_by' => $laboran->id,
            'user_read_reply_at' => $now->copy()->subDays(6),
            'created_at' => $now->copy()->subDays(8), 'updated_at' => $now->copy()->subDays(6),
        ]);
        $mkLaporan([
            'subjek' => 'Saran: tambahkan notifikasi saat pengajuan ruangan diputuskan',
            'pesan' => 'Agar tidak perlu cek berkala, usul ada notifikasi otomatis saat pengajuan disetujui/ditolak.',
            'status' => 'dibaca',
            'created_at' => $now->copy()->subDays(9), 'updated_at' => $now->copy()->subDays(9),
        ]);

        Announcement::create([
            'judul' => 'Jadwal pemeliharaan mesin absensi',
            'isi' => 'Mesin absensi BRAIL menjalani pemeliharaan rutin Jumat pukul 16:00–17:00.',
            'target' => 'mahasiswa', 'channel' => Announcement::CHANNEL_PENGUMUMAN, 'prioritas' => 'normal',
            'dibuat_oleh' => $laboran->id, 'is_active' => true,
        ]);
    }
}
