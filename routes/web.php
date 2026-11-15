<?php

use App\Http\Controllers\AbsensiManagementController;
use App\Http\Controllers\ActivationController;
use App\Http\Controllers\Admin\AccountImportController;
use App\Http\Controllers\Admin\AdminAiController;
use App\Http\Controllers\Admin\PblProjectController;
use App\Http\Controllers\Admin\SuggestionController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AdminLogController;
use App\Http\Controllers\AdminRuanganController;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DownloadController;
use App\Http\Controllers\DosenController;
use App\Http\Controllers\DosenPblController;
use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\HelpdeskChatController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\MesinIotController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OTPController;
use App\Http\Controllers\RuanganPengajuanController;
use App\Http\Controllers\SessionGuardController;
use App\Http\Controllers\UserProfileController;
use App\Http\Controllers\StudentAuthController;
use App\Http\Controllers\StudentPortalController;
use Illuminate\Support\Facades\Route;

// Landing page publik (pengenalan project) — URL bersih di root domain.
Route::get('/', [LandingController::class, 'index'])->name('beranda');
Route::get('/online', [LandingController::class, 'online'])->middleware('throttle:120,1')->name('beranda.online');
Route::get('/beranda', fn () => redirect('/', 301));
Route::view('/mobile/recaptcha', 'mobile.recaptcha')->name('mobile.recaptcha');
Route::get('/download', [DownloadController::class, 'index'])->name('download');
Route::get('/download/apk', [DownloadController::class, 'apk'])->name('download.apk');
Route::post('/suggestions', [LandingController::class, 'storeSuggestion'])
    ->middleware('throttle:8,1')
    ->name('suggestions.store');
Route::post('/helpdesk/chat', HelpdeskChatController::class)
    ->middleware('throttle:20,1')
    ->name('helpdesk.chat');

Route::middleware('guest')->group(function () {
    Route::get('/masuk', [AuthController::class, 'showAuthPage'])->name('login');
    Route::post('/masuk', [AuthController::class, 'login'])->name('login.post');
    // POST /login must be registered before any /login redirect — Route::redirect() matches ALL verbs.
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/login', fn () => redirect()->route('login', [], 301));

    // Pendaftaran manual web dinonaktifkan: GET dialihkan ke login gabungan, POST ditolak.
    Route::get('/register', fn () => redirect()->route('login'))->name('register');
    Route::get('/register/verify', fn () => redirect()->route('login'))->name('register.verify');
    Route::post('/register', [AuthController::class, 'register'])->name('register.post');
    Route::post('/register/resume', [AuthController::class, 'startRegistrationVerification'])
        ->middleware('throttle:8,1')
        ->name('register.resume.post');
    Route::post('/verify-registration', [AuthController::class, 'verifyRegistration'])->name('verify-registration.post');
    Route::post('/resend-verification', [AuthController::class, 'resendVerification'])->name('resend-verification.post');
    Route::post('/cancel-registration-verification', [AuthController::class, 'cancelRegistrationVerification'])->name('cancel-registration-verification.post');

    Route::get('/forgot-password', [AuthController::class, 'showAuthPage'])->name('forgot-password');
    Route::post('/forgot-password', [\App\Http\Controllers\PasswordResetLinkController::class, 'sendLink'])
        ->middleware('throttle:6,1')->name('forgot-password.post');
    Route::get('/verify-otp', [AuthController::class, 'showAuthPage'])->name('verify-otp');
    Route::post('/verify-otp', [OTPController::class, 'verifyOtp'])->middleware('throttle:10,1')->name('verify-otp.post');
    Route::post('/resend-otp', [OTPController::class, 'resendOtp'])->middleware('throttle:5,1')->name('resend-otp.post');
    Route::get('/reset-password', [AuthController::class, 'showAuthPage'])->name('reset-password');
    Route::post('/reset-password', [OTPController::class, 'resetPassword'])->middleware('throttle:10,1')->name('reset-password.post');
    Route::post('/cancel-password-reset', [OTPController::class, 'cancelPasswordReset'])->middleware('throttle:10,1')->name('cancel-password-reset.post');
});

// Link atur ulang password dari email — token menjadi kredensialnya, jadi tetap
// bisa diakses walau pengguna sedang login (jangan di dalam grup 'guest').
Route::get('/atur-ulang-password/selesai', [\App\Http\Controllers\PasswordResetLinkController::class, 'done'])->name('password.reset.done');
Route::get('/atur-ulang-password/{token}', [\App\Http\Controllers\PasswordResetLinkController::class, 'showForm'])->name('password.reset.form');
Route::post('/atur-ulang-password', [\App\Http\Controllers\PasswordResetLinkController::class, 'update'])
    ->middleware('throttle:10,1')->name('password.reset.update');

// Aktivasi mandiri akun hasil import — tidak membuat sesi login; tetap terbuka saat registrasi ditutup.
Route::prefix('aktivasi')->group(function () {
    Route::get('/', [ActivationController::class, 'show'])->name('activation.show');
    Route::get('/selesai', [ActivationController::class, 'done'])->name('activation.done');
    Route::post('/mulai', [ActivationController::class, 'start'])->middleware('throttle:10,1')->name('activation.start');
    Route::post('/lanjut', [ActivationController::class, 'resume'])->middleware('throttle:10,1')->name('activation.resume');
    Route::post('/profil', [ActivationController::class, 'saveProfile'])->middleware('throttle:10,1')->name('activation.profile');
    Route::post('/otp', [ActivationController::class, 'verifyOtp'])->middleware('throttle:10,1')->name('activation.otp');
    Route::post('/otp/kirim-ulang', [ActivationController::class, 'resendOtp'])->middleware('throttle:5,1')->name('activation.otp.resend');
    Route::post('/keluar', [ActivationController::class, 'leave'])->name('activation.leave');
});

// Verifikasi email mandiri — dipakai halaman login (guest/session) & dashboard (user login).
Route::post('/account-email', [AuthController::class, 'submitAccountEmail'])->middleware('throttle:10,1')->name('account-email.post');
Route::post('/account-email/check', [AuthController::class, 'checkAccountEmail'])->middleware('throttle:30,1')->name('account-email.check');
Route::post('/verify-account-email', [AuthController::class, 'verifyAccountEmail'])->middleware('throttle:15,1')->name('verify-account-email.post');
Route::post('/resend-account-verification', [AuthController::class, 'resendAccountVerification'])->middleware('throttle:5,1')->name('resend-account-verification.post');
Route::post('/cancel-account-verification', [AuthController::class, 'cancelAccountVerification'])->middleware('throttle:10,1')->name('cancel-account-verification.post');

Route::middleware('guest')->prefix('mahasiswa')->group(function () {
    Route::get('/masuk', [StudentAuthController::class, 'showLogin'])->name('mahasiswa.login');
    Route::post('/masuk', [StudentAuthController::class, 'login'])->middleware('throttle:10,1')->name('mahasiswa.login.post');
});

// Ganti password (password sementara hasil import) untuk akun non-mahasiswa.
// Tidak memaksa: pengguna tetap bebas beraktivitas, hanya diberi indikator peringatan.
Route::middleware(['auth'])->group(function () {
    Route::get('/ganti-password', [\App\Http\Controllers\AccountPasswordController::class, 'show'])->name('account.password.change');
    Route::post('/ganti-password', [\App\Http\Controllers\AccountPasswordController::class, 'update'])
        ->middleware('throttle:10,1')->name('account.password.update');
});

Route::middleware(['auth', 'approved', 'no.back'])->group(function () {
    Route::get('/dashboard', function () {
        $user = auth()->user();

        return match ($user->role) {
            'admin', 'laboran' => redirect('/admin/dashboard'),
            'dosen' => redirect('/dosen/dashboard'),
            'mahasiswa' => redirect()->route('mahasiswa.gate'),
            default => redirect()->route('login'),
        };
    })->name('dashboard');

    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/session/ping', [SessionGuardController::class, 'ping'])->name('session.ping');
    Route::get('/profiles/{user}', [UserProfileController::class, 'show'])->name('profiles.show');
    Route::get('/profiles/{user}/ringkas', [UserProfileController::class, 'ringkas'])->name('profiles.ringkas');
    Route::post('/profile/photo', [UserProfileController::class, 'updateOwnPhoto'])->name('profiles.photo.update');
    Route::post('/profile/kontak', [UserProfileController::class, 'updateOwnContact'])->name('profiles.contact.update');
    Route::post('/profile/akses-pin', [UserProfileController::class, 'updateOwnAccessPin'])
        ->middleware('throttle:10,1')->name('profiles.access-pin.update');
    Route::delete('/profile/photo', [UserProfileController::class, 'destroyOwnPhoto'])->name('profiles.photo.destroy');
    Route::post('/profile/password/reset-link', [\App\Http\Controllers\PasswordResetLinkController::class, 'sendLinkForSelf'])
        ->middleware('throttle:5,1')->name('profile.password.reset-link');

    // Web Push (VAPID) — subscribe/unsubscribe notifikasi browser, semua role.
    Route::prefix('push')->group(function () {
        Route::get('/vapid-key', [\App\Http\Controllers\PushSubscriptionController::class, 'vapidKey'])->name('push.vapid');
        Route::post('/subscribe', [\App\Http\Controllers\PushSubscriptionController::class, 'store'])->name('push.subscribe');
        Route::post('/unsubscribe', [\App\Http\Controllers\PushSubscriptionController::class, 'destroy'])->name('push.unsubscribe');
    });

    // Notifikasi pengumuman (admin / dosen)
    Route::prefix('notifications')->group(function () {
        Route::get('/feed', [NotificationController::class, 'feed'])->name('notifications.feed');
        Route::post('/announcements/{announcement}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
        Route::post('/announcements/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.readAll');
        Route::post('/ops/dismiss', [NotificationController::class, 'dismissOps'])->name('notifications.ops.dismiss');
    });

    Route::middleware('role:laboran,admin')->prefix('admin')->group(function () {
        Route::get('/dashboard', [AdminController::class, 'dashboard'])->middleware('perm:dashboard,view')->name('admin.dashboard');
        Route::get('/dashboard/data', [AdminController::class, 'dashboardData'])->middleware('perm:dashboard,view')->name('admin.dashboard.data');
        Route::get('/dashboard/mini-kalender', [AdminController::class, 'dashboardMiniCalJson'])->middleware('perm:dashboard,view')->name('admin.dashboard.minical');
        // Pengelolaan akun & import — kewenangan Admin (is_admin), bukan laboran operasional.
        Route::get('/users', [AdminController::class, 'users'])->middleware(['perm:users,view'])->name('admin.users');
        Route::get('/users/{role}', [AdminController::class, 'usersRole'])
            ->whereIn('role', ['mahasiswa', 'dosen', 'laboran'])
            ->middleware(['perm:users,view'])->name('admin.users.role');
        Route::post('/users', [AdminController::class, 'storeUser'])->middleware(['perm:users,create', 'admin.only'])->name('admin.users.store');
        Route::put('/users/{user}', [AdminController::class, 'updateUser'])->middleware(['perm:users,update', 'admin.only'])->name('admin.users.update');
        Route::delete('/users/{user}', [AdminController::class, 'destroyUser'])->middleware(['perm:users,delete', 'admin.only'])->name('admin.users.destroy');

        // Import akun (hak = Tambah User). Kode aktivasi hanya di kartu sekali unduh.
        Route::middleware(['perm:users,create', 'admin.only'])->prefix('users/import')->group(function () {
            Route::get('/', [AccountImportController::class, 'index'])->name('admin.users.import');
            Route::get('/template/{type}.{format}', [AccountImportController::class, 'template'])
                ->whereIn('type', \App\Support\AccountImport\AccountImportTemplate::TYPES)
                ->whereIn('format', ['csv', 'xlsx'])
                ->name('admin.users.import.template');
            Route::post('/preview', [AccountImportController::class, 'preview'])->middleware('throttle:20,1')->name('admin.users.import.preview');
            Route::get('/{token}', [AccountImportController::class, 'show'])->where('token', '[A-Za-z0-9]{40}')->name('admin.users.import.show');
            Route::post('/{token}/apply', [AccountImportController::class, 'apply'])->where('token', '[A-Za-z0-9]{40}')->name('admin.users.import.apply');
            Route::get('/{token}/laporan.xlsx', [AccountImportController::class, 'report'])->where('token', '[A-Za-z0-9]{40}')->name('admin.users.import.report');
            Route::post('/{token}/kartu', [AccountImportController::class, 'cards'])->where('token', '[A-Za-z0-9]{40}')->name('admin.users.import.cards');
        });
        Route::post('/users/{user}/activation-code', [AccountImportController::class, 'regenerateCode'])
            ->middleware(['perm:users,update', 'admin.only', 'throttle:20,1'])
            ->name('admin.users.activation-code');

        Route::get('/attendance', [AbsensiManagementController::class, 'rekap'])->middleware('perm:attendance,view')->name('admin.attendance');
        Route::get('/attendance/snapshot', [AbsensiManagementController::class, 'snapshot'])->middleware('perm:attendance,view')->name('admin.attendance.snapshot');
        Route::get('/attendance/live', [AbsensiManagementController::class, 'rekapLive'])->middleware('perm:attendance,view')->name('admin.attendance.live');
        Route::get('/attendance/export', [AbsensiManagementController::class, 'exportRekapExcel'])->middleware('perm:attendance,view')->name('admin.attendance.export');
        Route::get('/attendance/print', [AbsensiManagementController::class, 'printRekap'])->middleware('perm:attendance,view')->name('admin.attendance.print');
        Route::get('/absensi/pengaturan', [AbsensiManagementController::class, 'settings'])->middleware(['perm:settings,view', 'admin.only'])->name('admin.absensi.settings');
        Route::post('/absensi/sesi', [AbsensiManagementController::class, 'updateSesi'])->middleware(['perm:settings,update', 'admin.only'])->name('admin.absensi.sesi');
        Route::post('/absensi/kebijakan', [AbsensiManagementController::class, 'updateKebijakan'])->middleware(['perm:settings,update', 'admin.only'])->name('admin.absensi.kebijakan');
        Route::post('/absensi/tutup-sesi', [AbsensiManagementController::class, 'runCloseSessions'])->middleware(['perm:settings,update', 'admin.only'])->name('admin.absensi.tutup-sesi');
        Route::post('/absensi/generate-alfa', [AbsensiManagementController::class, 'runGenerateAlfa'])->middleware(['perm:settings,update', 'admin.only'])->name('admin.absensi.generate-alfa');
        Route::post('/absensi/libur', [AbsensiManagementController::class, 'storeLibur'])->middleware(['perm:settings,update', 'admin.only'])->name('admin.absensi.libur');
        Route::delete('/absensi/libur/{libur}', [AbsensiManagementController::class, 'destroyLibur'])->middleware(['perm:settings,update', 'admin.only'])->name('admin.absensi.libur.destroy');
        Route::post('/absensi/prodi', [AbsensiManagementController::class, 'storeProdi'])->middleware(['perm:settings,update', 'admin.only'])->name('admin.absensi.prodi');
        Route::get('/kalender', [AbsensiManagementController::class, 'kalender'])->middleware('perm:attendance,view')->name('admin.kalender');
        Route::post('/kalender/agenda', [AbsensiManagementController::class, 'storeLibur'])->middleware('perm:attendance,update')->name('admin.kalender.agenda');
        Route::patch('/kalender/agenda/{libur}', [AbsensiManagementController::class, 'updateLibur'])->middleware('perm:attendance,update')->name('admin.kalender.agenda.update');
        Route::delete('/kalender/agenda/{libur}', [AbsensiManagementController::class, 'destroyLibur'])->middleware('perm:attendance,update')->name('admin.kalender.agenda.destroy');
        Route::get('/absensi/alfa', [AbsensiManagementController::class, 'alfa'])->middleware('perm:attendance,view')->name('admin.absensi.alfa');
        Route::get('/absensi/tidak-lengkap', [AbsensiManagementController::class, 'tidakLengkap'])->middleware('perm:attendance,view')->name('admin.absensi.tidak-lengkap');
        Route::post('/absensi/harian/{harian}/koreksi', [AbsensiManagementController::class, 'koreksiHarian'])->middleware('perm:attendance,update')->name('admin.absensi.koreksi');
        Route::post('/absensi/alfa/{alfa}/batalkan', [AbsensiManagementController::class, 'batalkanAlfa'])->middleware('perm:attendance,update')->name('admin.absensi.alfa.batalkan');
        Route::post('/absensi/tidak-lengkap/{harian}/selesai', [AbsensiManagementController::class, 'selesaiTidakLengkap'])->middleware('perm:attendance,update')->name('admin.absensi.tidak-lengkap.selesai');
        Route::post('/absensi/komentar', [AbsensiManagementController::class, 'storeKomentar'])->name('admin.absensi.komentar');
        Route::get('/pbl', [PblProjectController::class, 'index'])->middleware('perm:users,view')->name('admin.pbl.index');
        Route::get('/pbl/search.json', [PblProjectController::class, 'search'])->middleware('perm:users,view')->name('admin.pbl.search');
        Route::post('/pbl', [PblProjectController::class, 'store'])->middleware('perm:users,update')->name('admin.pbl.store');
        Route::put('/pbl/{pbl}', [PblProjectController::class, 'update'])->middleware('perm:users,update')->name('admin.pbl.update');
        Route::delete('/pbl/{pbl}', [PblProjectController::class, 'destroy'])->middleware('perm:users,update')->name('admin.pbl.destroy');
        Route::post('/pbl/assign-mahasiswa', [PblProjectController::class, 'assignMahasiswa'])->middleware('perm:users,update')->name('admin.pbl.assign-mahasiswa');
        Route::post('/pbl/{pbl}/staff', [PblProjectController::class, 'updateStaff'])->middleware('perm:users,update')->name('admin.pbl.update-staff');
        Route::post('/pbl/{pbl}/ketua', [PblProjectController::class, 'assignKetua'])->middleware('perm:users,update')->name('admin.pbl.ketua.store');
        Route::post('/pbl/{pbl}/wakil', [PblProjectController::class, 'assignWakil'])->middleware('perm:users,update')->name('admin.pbl.wakil.store');
        Route::post('/pbl/{pbl}/clear-role', [PblProjectController::class, 'clearTeamRole'])->middleware('perm:users,update')->name('admin.pbl.clear-role');
        Route::post('/pbl/mahasiswa/{user}/clear-tim', [PblProjectController::class, 'clearMahasiswaTim'])->middleware('perm:users,update')->name('admin.pbl.clear-tim');
        Route::post('/pbl/import-projects', [PblProjectController::class, 'importProjectsCsv'])->middleware('perm:users,update')->name('admin.pbl.import-projects');
        Route::post('/pbl/sync-mahasiswa-tim', [PblProjectController::class, 'syncMahasiswaTimCsv'])->middleware('perm:users,update')->name('admin.pbl.sync-mahasiswa-tim');
        Route::get('/pbl/csv-template', [PblProjectController::class, 'downloadCsvTemplate'])->middleware('perm:users,view')->name('admin.pbl.csv-template');
        Route::get('/pbl/{pbl}/aktivitas', [PblProjectController::class, 'aktivitas'])->middleware('perm:users,view')->name('admin.pbl.aktivitas');
        Route::post('/pbl/{pbl}/media', [PblProjectController::class, 'updateMedia'])->middleware('perm:users,update')->name('admin.pbl.media');

        Route::get('/fingerprint', [AdminController::class, 'fingerprint'])->middleware('perm:fingerprint,view')->name('admin.fingerprint');
        Route::get('/fingerprint/snapshot', [AdminController::class, 'fingerprintSnapshot'])->middleware('perm:fingerprint,view')->name('admin.fingerprint.snapshot');
        // Monitoring digabung ke halaman Mesin Absensi — redirect agar tautan lama tetap jalan.
        Route::get('/monitoring', [AdminController::class, 'monitoring'])->middleware('perm:monitoring,view')->name('admin.monitoring');
        Route::get('/monitoring/feed', [AdminController::class, 'monitoringFeed'])->middleware('perm:monitoring,view')->name('admin.monitoring.feed');
        // Pusat Kendali — metrik terpadu lintas sistem; khusus admin karena
        // memuat presence pengguna + antrian approval.
        Route::get('/pusat-kendali', [AdminController::class, 'pusatKendali'])->middleware(['perm:monitoring,view', 'admin.only'])->name('admin.pusat-kendali');
        Route::get('/pusat-kendali/feed', [AdminController::class, 'pusatKendaliFeed'])->middleware(['perm:monitoring,view', 'admin.only'])->name('admin.pusat-kendali.feed');
        Route::get('/settings', [AdminController::class, 'settings'])->middleware(['perm:settings,view', 'admin.only'])->name('admin.settings');
        Route::post('/settings', [AdminController::class, 'updateSettings'])->middleware(['perm:settings,update', 'admin.only'])->name('admin.settings.update');
        Route::post('/settings/branding', [AdminController::class, 'updateBranding'])->middleware(['perm:settings,update', 'admin.only'])->name('admin.settings.branding');
        Route::post('/settings/feature-badges', [AdminController::class, 'updateFeatureBadges'])->middleware(['perm:settings,update', 'admin.only'])->name('admin.settings.feature-badges');
        Route::delete('/settings/branding', [AdminController::class, 'resetBranding'])->middleware(['perm:settings,update', 'admin.only'])->name('admin.settings.branding.reset');
        // Toggle registrasi dihapus — registrasi publik tertutup permanen, akun dibuat lewat import.
        Route::get('/reports', [AdminController::class, 'reports'])->middleware('perm:reports,view')->name('admin.reports');
        Route::get('/reports/export/excel', [AdminController::class, 'exportReportExcel'])->middleware('perm:reports,view')->name('admin.reports.export.excel');
        Route::get('/reports/export/word', [AdminController::class, 'exportReportWord'])->middleware('perm:reports,view')->name('admin.reports.export.word');
        Route::get('/reports/print', [AdminController::class, 'printReport'])->middleware('perm:reports,view')->name('admin.reports.print');
        Route::get('/reports/student/{user}/detail', [AdminController::class, 'periodicStudentDetail'])->middleware('perm:reports,view')->name('admin.reports.student-detail');
        Route::get('/notifications', [AdminController::class, 'notifications'])->middleware('perm:notifications,view')->name('admin.notifications');

        Route::get('/mesin', [MesinIotController::class, 'index'])->middleware('perm:mesin,view')->name('admin.mesin.index');
        Route::get('/mesin/panduan', [MesinIotController::class, 'panduan'])->middleware('perm:mesin,view')->name('admin.mesin.panduan');
        Route::get('/mesin/{mesin}/conn', [MesinIotController::class, 'conn'])->middleware('perm:mesin,view')->name('admin.mesin.conn');
        Route::get('/mesin/{mesin}/sketch', [MesinIotController::class, 'sketch'])->middleware('perm:mesin,view')->name('admin.mesin.sketch');
        Route::post('/mesin', [MesinIotController::class, 'store'])->middleware(['perm:mesin,create', 'admin.only'])->name('admin.mesin.store');
        Route::put('/mesin/{mesin}', [MesinIotController::class, 'update'])->middleware(['perm:mesin,update', 'admin.only'])->name('admin.mesin.update');
        Route::post('/mesin/{mesin}/toggle', [MesinIotController::class, 'toggleActive'])->middleware(['perm:mesin,update', 'admin.only'])->name('admin.mesin.toggle');
        Route::post('/mesin/{mesin}/regenerate', [MesinIotController::class, 'regenerate'])->middleware(['perm:mesin,update', 'admin.only'])->name('admin.mesin.regenerate');
        Route::delete('/mesin/{mesin}', [MesinIotController::class, 'destroy'])->middleware(['perm:mesin,delete', 'admin.only'])->name('admin.mesin.destroy');
        Route::post('/mesin/{mesin}/ruangan', [AdminRuanganController::class, 'bindMesin'])->middleware(['perm:mesin,update', 'admin.only'])->name('admin.mesin.bind-ruangan');

        // SMART BRAIL — BMS ruangan
        Route::get('/ruangan', [AdminRuanganController::class, 'index'])->middleware('perm:ruangan,view')->name('admin.ruangan.index');
        Route::post('/ruangan', [AdminRuanganController::class, 'store'])->middleware('perm:ruangan,create')->name('admin.ruangan.store');
        Route::put('/ruangan/{ruangan}', [AdminRuanganController::class, 'update'])->middleware('perm:ruangan,update')->name('admin.ruangan.update');
        Route::get('/pengajuan', [RuanganPengajuanController::class, 'indexLaboran'])->middleware('perm:ruangan_persetujuan,view')->name('admin.pengajuan.index');
        Route::post('/pengajuan/{pengajuan}/setujui', [RuanganPengajuanController::class, 'setujui'])->middleware('perm:ruangan_persetujuan,update')->name('admin.pengajuan.setujui');
        Route::post('/pengajuan/{pengajuan}/tolak', [RuanganPengajuanController::class, 'tolak'])->middleware('perm:ruangan_persetujuan,update')->name('admin.pengajuan.tolak');
        Route::post('/pengajuan/{pengajuan}/pengganti', [RuanganPengajuanController::class, 'tawarkanPengganti'])->middleware('perm:ruangan_persetujuan,update')->name('admin.pengajuan.pengganti');
        Route::post('/pengajuan/{pengajuan}/cabut', [RuanganPengajuanController::class, 'cabut'])->middleware('perm:ruangan_persetujuan,update')->name('admin.pengajuan.cabut');
        Route::get('/petugas', [AdminRuanganController::class, 'petugasIndex'])->middleware('perm:petugas,view')->name('admin.petugas.index');
        Route::post('/petugas', [AdminRuanganController::class, 'petugasStore'])->middleware('perm:petugas,create')->name('admin.petugas.store');
        Route::put('/petugas/{petugas}', [AdminRuanganController::class, 'petugasUpdate'])->middleware('perm:petugas,update')->name('admin.petugas.update');

        Route::get('/log/absensi', [AdminLogController::class, 'absensi'])->middleware('perm:log_absensi,view')->name('admin.log.absensi');
        Route::get('/log/absensi/snapshot', [AdminLogController::class, 'snapshot'])->middleware('perm:log_absensi,view')->name('admin.log.absensi.snapshot');
        Route::get('/announcements/{announcement}/json', [AnnouncementController::class, 'showJson'])->middleware('perm:announcements,view')->name('admin.announcements.json');
        Route::get('/announcements', [AnnouncementController::class, 'index'])->middleware('perm:announcements,view')->name('admin.announcements.index');
        Route::post('/announcements', [AnnouncementController::class, 'store'])->middleware('perm:announcements,create')->name('admin.announcements.store');
        Route::put('/announcements/{announcement}', [AnnouncementController::class, 'update'])->middleware('perm:announcements,update')->name('admin.announcements.update');
        Route::post('/announcements/{announcement}/toggle', [AnnouncementController::class, 'toggle'])->middleware('perm:announcements,update')->name('admin.announcements.toggle');
        Route::delete('/announcements/{announcement}', [AnnouncementController::class, 'destroy'])->middleware('perm:announcements,delete')->name('admin.announcements.destroy');

        Route::post('/fingerprint/{user}/assign', [AdminController::class, 'assignFingerId'])->middleware('perm:fingerprint,update')->name('admin.fingerprint.assign');
        Route::delete('/fingerprint/{user}/reset', [AdminController::class, 'resetFingerId'])->middleware('perm:fingerprint,delete')->name('admin.fingerprint.reset');
        Route::post('/fingerprint/{user}/clear-enroll', [AdminController::class, 'clearFingerprintEnroll'])->middleware('perm:fingerprint,update')->name('admin.fingerprint.clear-enroll');
        Route::post('/fingerprint/backfill-shift', [AdminController::class, 'backfillMahasiswaShift'])->middleware('perm:fingerprint,update')->name('admin.fingerprint.backfill-shift');
        Route::post('/fingerprint/sync-mode', [AdminController::class, 'updateFingerprintSyncMode'])->middleware('perm:fingerprint,update')->name('admin.fingerprint.sync-mode');
        Route::post('/fingerprint/sync-distribute', [AdminController::class, 'triggerFingerprintSyncBurst'])->middleware('perm:fingerprint,update')->name('admin.fingerprint.sync-distribute');

        // Saran & masukan dari landing publik.
        Route::get('/suggestions', [SuggestionController::class, 'index'])->middleware('perm:suggestions,view')->name('admin.suggestions.index');
        Route::get('/suggestions/feed', [SuggestionController::class, 'feed'])->middleware('perm:suggestions,view')->name('admin.suggestions.feed');
        Route::get('/suggestions/{suggestion}/lampiran', [SuggestionController::class, 'lampiran'])->middleware('perm:suggestions,view')->name('admin.suggestions.lampiran');
        Route::patch('/suggestions/{suggestion}', [SuggestionController::class, 'updateStatus'])->middleware('perm:suggestions,update')->name('admin.suggestions.update');
        Route::post('/suggestions/{suggestion}/reply', [SuggestionController::class, 'reply'])->middleware('perm:suggestions,update')->name('admin.suggestions.reply');
        Route::delete('/suggestions/{suggestion}/reply', [SuggestionController::class, 'clearReply'])->middleware('perm:suggestions,update')->name('admin.suggestions.reply.clear');
        Route::delete('/suggestions/{suggestion}', [SuggestionController::class, 'destroy'])->middleware('perm:suggestions,delete')->name('admin.suggestions.destroy');

        // Endpoint approve/reject registrasi dinonaktifkan — tidak ada pemakai nyata setelah
        // registrasi publik tertutup; akun dibuat admin (import/tambah manual) langsung approved.
        // Metode controller dipertahankan untuk arsip/kompatibilitas namun tidak lagi dirutekan.
        Route::post('/users/{user}/toggle-active', [AdminController::class, 'toggleUserActive'])->middleware('perm:users,update')->name('admin.users.toggle-active');
        Route::post('/users/{user}/unlock-login', [AdminController::class, 'unlockUserLogin'])->middleware('perm:users,update')->name('admin.users.unlock-login');

        Route::get('/ai', [AdminAiController::class, 'index'])->middleware('perm:ai_assistant,view')->name('admin.ai');
        Route::get('/ai/training', [AdminAiController::class, 'training'])->middleware('perm:ai_assistant,view')->name('admin.ai.training');
        Route::post('/ai/training/knowledge', [AdminAiController::class, 'saveKnowledge'])->middleware(['perm:ai_assistant,update', 'throttle:10,1'])->name('admin.ai.training.knowledge');
        Route::post('/ai/training/respons/hapus', [AdminAiController::class, 'deleteFeedback'])->middleware(['perm:ai_assistant,update', 'throttle:30,1'])->name('admin.ai.training.respons.delete');
        Route::get('/ai/conversations', [AdminAiController::class, 'conversations'])->middleware('perm:ai_assistant,view')->name('admin.ai.conversations');
        Route::post('/ai/conversations', [AdminAiController::class, 'storeConversation'])->middleware(['perm:ai_assistant,view', 'throttle:30,1'])->name('admin.ai.conversations.store');
        Route::get('/ai/conversations/{conversation}', [AdminAiController::class, 'showConversation'])->middleware('perm:ai_assistant,view')->name('admin.ai.conversations.show');
        Route::patch('/ai/conversations/{conversation}', [AdminAiController::class, 'updateConversation'])->middleware(['perm:ai_assistant,view', 'throttle:30,1'])->name('admin.ai.conversations.update');
        Route::delete('/ai/conversations/{conversation}', [AdminAiController::class, 'destroyConversation'])->middleware(['perm:ai_assistant,view', 'throttle:30,1'])->name('admin.ai.conversations.destroy');
        Route::post('/ai/chat', [AdminAiController::class, 'chat'])->middleware(['perm:ai_assistant,view', 'throttle:30,1'])->name('admin.ai.chat');
        Route::post('/ai/chat/stream', [AdminAiController::class, 'stream'])->middleware(['perm:ai_assistant,view', 'throttle:30,1'])->name('admin.ai.chat.stream');
        Route::post('/ai/chat/attachment', [AdminAiController::class, 'uploadAttachment'])->middleware(['perm:ai_assistant,view', 'throttle:30,1'])->name('admin.ai.chat.attachment');
        Route::get('/ai/chat/attachment/{id}', [AdminAiController::class, 'serveAttachment'])->middleware('perm:ai_assistant,view')->name('admin.ai.chat.attachment.serve');
        Route::post('/ai/actions/{proposal}/confirm', [AdminAiController::class, 'confirm'])->middleware(['perm:ai_assistant,view', 'throttle:40,1'])->name('admin.ai.confirm');
        Route::post('/ai/actions/{proposal}/cancel', [AdminAiController::class, 'cancel'])->middleware(['perm:ai_assistant,view', 'throttle:40,1'])->name('admin.ai.cancel');
    });

    Route::middleware('role:dosen')->prefix('dosen')->group(function () {
        Route::get('/dashboard', [DosenController::class, 'dashboard'])->middleware('perm:dashboard,view')->name('dosen.dashboard');
        Route::get('/dashboard/data', [DosenController::class, 'dashboardData'])->middleware('perm:dashboard,view')->name('dosen.dashboard.data');
        Route::get('/dashboard/mini-kalender', [DosenController::class, 'dashboardMiniCalJson'])->middleware('perm:dashboard,view')->name('dosen.dashboard.minical');
        Route::get('/ai', [AdminAiController::class, 'index'])->middleware('perm:ai_assistant,view')->name('dosen.ai');
        Route::get('/ai/conversations', [AdminAiController::class, 'conversations'])->middleware('perm:ai_assistant,view')->name('dosen.ai.conversations');
        Route::post('/ai/conversations', [AdminAiController::class, 'storeConversation'])->middleware(['perm:ai_assistant,view', 'throttle:30,1'])->name('dosen.ai.conversations.store');
        Route::post('/ai/actions/{proposal}/confirm', [AdminAiController::class, 'confirm'])->middleware(['perm:ai_assistant,view', 'throttle:30,1'])->name('dosen.ai.confirm');
        Route::post('/ai/actions/{proposal}/cancel', [AdminAiController::class, 'cancel'])->middleware(['perm:ai_assistant,view', 'throttle:30,1'])->name('dosen.ai.cancel');
        Route::get('/ai/conversations/{conversation}', [AdminAiController::class, 'showConversation'])->middleware('perm:ai_assistant,view')->name('dosen.ai.conversations.show');
        Route::patch('/ai/conversations/{conversation}', [AdminAiController::class, 'updateConversation'])->middleware(['perm:ai_assistant,view', 'throttle:30,1'])->name('dosen.ai.conversations.update');
        Route::delete('/ai/conversations/{conversation}', [AdminAiController::class, 'destroyConversation'])->middleware(['perm:ai_assistant,view', 'throttle:30,1'])->name('dosen.ai.conversations.destroy');
        Route::post('/ai/chat/stream', [AdminAiController::class, 'stream'])->middleware(['perm:ai_assistant,view', 'throttle:30,1'])->name('dosen.ai.chat.stream');
        Route::post('/ai/chat/attachment', [AdminAiController::class, 'uploadAttachment'])->middleware(['perm:ai_assistant,view', 'throttle:30,1'])->name('dosen.ai.chat.attachment');
        Route::get('/ai/chat/attachment/{id}', [AdminAiController::class, 'serveAttachment'])->middleware('perm:ai_assistant,view')->name('dosen.ai.chat.attachment.serve');
        Route::get('/absensi', [AbsensiManagementController::class, 'rekap'])->middleware('perm:dosen_absensi,view')->name('dosen.absensi');
        Route::get('/absensi/snapshot', [AbsensiManagementController::class, 'snapshot'])->middleware('perm:dosen_absensi,view')->name('dosen.absensi.snapshot');
        Route::get('/absensi/export', [AbsensiManagementController::class, 'exportRekapExcel'])->middleware('perm:dosen_absensi,view')->name('dosen.absensi.export');
        Route::get('/absensi/print', [AbsensiManagementController::class, 'printRekap'])->middleware('perm:dosen_absensi,view')->name('dosen.absensi.print');
        Route::get('/absensi/alfa', [AbsensiManagementController::class, 'alfa'])->middleware('perm:dosen_absensi,view')->name('dosen.absensi.alfa');
        Route::get('/absensi/tidak-lengkap', [AbsensiManagementController::class, 'tidakLengkap'])->middleware('perm:dosen_absensi,view')->name('dosen.absensi.tidak-lengkap');
        Route::post('/absensi/harian/{harian}/koreksi', [AbsensiManagementController::class, 'koreksiHarian'])->middleware('perm:dosen_absensi,update')->name('dosen.absensi.koreksi');
        Route::post('/absensi/alfa/{alfa}/batalkan', [AbsensiManagementController::class, 'batalkanAlfa'])->middleware('perm:dosen_absensi,update')->name('dosen.absensi.alfa.batalkan');
        Route::post('/absensi/tidak-lengkap/{harian}/selesai', [AbsensiManagementController::class, 'selesaiTidakLengkap'])->middleware('perm:dosen_absensi,update')->name('dosen.absensi.tidak-lengkap.selesai');
        Route::post('/absensi/komentar', [AbsensiManagementController::class, 'storeKomentar'])->name('dosen.absensi.komentar');
        Route::get('/reports', [DosenController::class, 'reports'])->middleware('perm:dosen_absensi,view')->name('dosen.reports');
        Route::get('/reports/export/excel', [DosenController::class, 'exportReportExcel'])->middleware('perm:dosen_absensi,view')->name('dosen.reports.export.excel');
        Route::get('/reports/export/word', [DosenController::class, 'exportReportWord'])->middleware('perm:dosen_absensi,view')->name('dosen.reports.export.word');
        Route::get('/reports/print', [DosenController::class, 'printReport'])->middleware('perm:dosen_absensi,view')->name('dosen.reports.print');
        Route::get('/reports/student/{user}/detail', [DosenController::class, 'periodicStudentDetail'])->middleware('perm:dosen_absensi,view')->name('dosen.reports.student-detail');
        Route::get('/notifikasi', [DosenController::class, 'notifikasi'])->middleware('perm:dashboard,view')->name('dosen.notifikasi');
        Route::get('/mahasiswa', [DosenController::class, 'mahasiswa'])->middleware('perm:dosen_mahasiswa,view')->name('dosen.mahasiswa');
        Route::get('/mahasiswa/export.csv', [DosenController::class, 'exportMahasiswaCsv'])->middleware('perm:dosen_mahasiswa,view')->name('dosen.mahasiswa.export');
        Route::get('/pbl', [DosenPblController::class, 'index'])->middleware('perm:dosen_pbl,view')->name('dosen.pbl.index');
        Route::post('/pbl', [DosenPblController::class, 'store'])->middleware('perm:dosen_pbl,create')->name('dosen.pbl.store');
        Route::post('/pbl/{pbl}/media', [DosenPblController::class, 'updateMedia'])->middleware('perm:dosen_pbl,update')->name('dosen.pbl.media');
        Route::post('/pbl/{pbl}/anggota', [DosenPblController::class, 'storeAnggota'])->middleware('perm:dosen_pbl,create')->name('dosen.pbl.anggota.store');
        Route::post('/pbl/{pbl}/ketua', [DosenPblController::class, 'storeKetua'])->middleware('perm:dosen_pbl,create')->name('dosen.pbl.ketua.store');
        Route::post('/pbl/{pbl}/wakil', [DosenPblController::class, 'storeWakil'])->middleware('perm:dosen_pbl,create')->name('dosen.pbl.wakil.store');
        Route::post('/pbl/{pbl}/clear-role', [DosenPblController::class, 'clearTeamRole'])->middleware('perm:dosen_pbl,delete')->name('dosen.pbl.clear-role');
        Route::delete('/pbl/{pbl}/members/{user}', [DosenPblController::class, 'destroyMember'])->middleware('perm:dosen_pbl,delete')->name('dosen.pbl.members.destroy');
        Route::get('/pbl/{pbl}/aktivitas', [DosenPblController::class, 'aktivitas'])->middleware('perm:dosen_pbl,view')->name('dosen.pbl.aktivitas');
        Route::get('/announcements', [DosenController::class, 'announcements'])->middleware('perm:announcements,view')->name('dosen.announcements');
        Route::post('/announcements', [DosenController::class, 'storeAnnouncement'])->middleware('perm:announcements,create')->name('dosen.announcements.store');
        Route::get('/profile', [DosenController::class, 'profile'])->middleware('perm:dosen_profile,view')->name('dosen.profile');
        Route::get('/saran', [FeedbackController::class, 'index'])->name('dosen.feedback.index');
        Route::post('/saran', [FeedbackController::class, 'store'])
            ->middleware('throttle:12,1')
            ->name('dosen.feedback.store');
        Route::post('/saran/{suggestion}/baca', [FeedbackController::class, 'markReplyRead'])->name('dosen.feedback.read');
        Route::post('/saran/{suggestion}/balas', [FeedbackController::class, 'replySuggestion'])->middleware('throttle:12,1')->name('dosen.feedback.reply');
        Route::get('/ruangan', [RuanganPengajuanController::class, 'indexDosen'])->middleware('perm:dosen_pbl,view')->name('dosen.ruangan');
    });

    Route::get('/app-nonaktif', fn () => redirect()->route('mahasiswa.beranda'))->name('mahasiswa.gate');
    Route::middleware(['role:mahasiswa'])->prefix('mahasiswa')->group(function () {
        Route::get('/ganti-password', [StudentPortalController::class, 'showChangePassword'])->name('mahasiswa.password.change');
        Route::post('/ganti-password', [StudentPortalController::class, 'updatePassword'])->middleware('throttle:10,1')->name('mahasiswa.password.update');
        Route::post('/password/kirim-tautan-reset', [\App\Http\Controllers\PasswordResetLinkController::class, 'sendLinkForSelf'])
            ->middleware('throttle:5,1')->name('mahasiswa.password.reset-link');
        Route::post('/kontak/tunda', [StudentPortalController::class, 'dismissContactPrompt'])->name('mahasiswa.contact-prompt.dismiss');
        Route::get('/beranda', [StudentPortalController::class, 'dashboard'])->name('mahasiswa.beranda');
        Route::get('/absensi', [StudentPortalController::class, 'attendance'])->name('mahasiswa.absensi');
        Route::get('/kalender', [StudentPortalController::class, 'calendarPage'])->name('mahasiswa.calendar');
        Route::get('/kalender/bulan', [StudentPortalController::class, 'calendarMonth'])->name('mahasiswa.calendar.month');
        Route::get('/panduan', [StudentPortalController::class, 'guide'])->name('mahasiswa.guide');
        Route::get('/segera-hadir', [StudentPortalController::class, 'comingSoon'])->name('mahasiswa.coming-soon');
        Route::get('/profil', [StudentPortalController::class, 'profile'])->name('mahasiswa.profile');
        Route::post('/profil/kontak', [StudentPortalController::class, 'updateContact'])->middleware('throttle:10,1')->name('mahasiswa.profile.contact');
        Route::post('/profil/email/kirim-otp', [StudentPortalController::class, 'resendEmailOtp'])->middleware('throttle:5,1')->name('mahasiswa.profile.email.resend');
        Route::post('/profil/email/verifikasi', [StudentPortalController::class, 'verifyEmailOtp'])->middleware('throttle:10,1')->name('mahasiswa.profile.email.verify');
        Route::get('/tim', [StudentPortalController::class, 'team'])->name('mahasiswa.team');
        Route::get('/notifikasi', [StudentPortalController::class, 'notifications'])->name('mahasiswa.notifications');
        Route::get('/notifikasi/feed', [StudentPortalController::class, 'notificationsFeed'])->name('mahasiswa.notifications.feed');
        Route::post('/notifikasi/baca-semua', [StudentPortalController::class, 'readAllAnnouncements'])->name('mahasiswa.notifications.read-all');
        Route::post('/notifikasi/ops/baca', [StudentPortalController::class, 'readOpsNotification'])->name('mahasiswa.notifications.ops-read');
        Route::post('/notifikasi/{announcement}/baca', [StudentPortalController::class, 'readAnnouncement'])->name('mahasiswa.notifications.read');
        Route::get('/saran', [StudentPortalController::class, 'suggestions'])->name('mahasiswa.suggestions');
        Route::post('/saran', [StudentPortalController::class, 'storeSuggestion'])->middleware('throttle:12,1')->name('mahasiswa.suggestions.store');
        Route::post('/saran/{suggestion}/baca', [StudentPortalController::class, 'readSuggestionReply'])->name('mahasiswa.suggestions.read');
        Route::get('/ruangan', [RuanganPengajuanController::class, 'indexMahasiswa'])->name('mahasiswa.ruangan');
        Route::get('/ruangan/ketersediaan', [RuanganPengajuanController::class, 'ketersediaanMahasiswa'])->middleware('throttle:30,1')->name('mahasiswa.ruangan.ketersediaan');
        Route::post('/ruangan', [RuanganPengajuanController::class, 'storeMahasiswa'])->middleware('throttle:12,1')->name('mahasiswa.ruangan.store');
        Route::post('/ruangan/{pengajuan}/terima-pengganti', [RuanganPengajuanController::class, 'terimaPengganti'])->name('mahasiswa.ruangan.terima-pengganti');
        Route::post('/ruangan/{pengajuan}/batal', [RuanganPengajuanController::class, 'batalkan'])->name('mahasiswa.ruangan.batal');
        Route::post('/bantuan/ai', [StudentPortalController::class, 'helpAi'])->middleware('throttle:20,1')->name('mahasiswa.help.ai');
        Route::get('/bantuan/konteks', [StudentPortalController::class, 'helpContext'])->middleware('throttle:30,1')->name('mahasiswa.help.context');
        Route::post('/bantuan/feedback', [StudentPortalController::class, 'helpFeedback'])->middleware('throttle:60,1')->name('mahasiswa.help.feedback');
        Route::post('/bantuan/lampiran', [StudentPortalController::class, 'uploadLampiran'])->middleware('throttle:10,1')->name('mahasiswa.help.lampiran');
        Route::get('/saran/{suggestion}/lampiran', [StudentPortalController::class, 'suggestionLampiran'])->name('mahasiswa.suggestions.lampiran');
        Route::post('/keluar', [StudentAuthController::class, 'logout'])->name('mahasiswa.logout');
        Route::redirect('/app', '/mahasiswa/beranda');
        Route::redirect('/app/{path}', '/mahasiswa/beranda')->where('path', '.*');
    });
});

// Preview terisolasi area mahasiswa — hanya lingkungan lokal.
// Merender view aplikasi dengan database fixture SQLite (storage/app/student-preview.sqlite).
// Tidak membuat session login dan tidak menyentuh database operasional.
if (app()->isLocal()) {
    Route::get('/dev/preview/mahasiswa/api/ketersediaan', [\App\Http\Controllers\Dev\StudentPreviewController::class, 'ketersediaan'])
        ->name('dev.preview.mahasiswa.ketersediaan');

    Route::get('/dev/preview/mahasiswa/{page?}', [\App\Http\Controllers\Dev\StudentPreviewController::class, 'handle'])
        ->where('page', 'beranda|absensi|kalender|tim|profil|ganti-password|pengajuan|riwayat|laporan')
        ->name('dev.preview.mahasiswa');

    // Preview terisolasi area admin (dashboard + halaman data akun per-role).
    Route::get('/dev/preview/admin/api/dashboard-data', [\App\Http\Controllers\Dev\StudentPreviewController::class, 'adminDashboardData'])
        ->name('dev.preview.admin.dashboard-data');

    Route::get('/dev/preview/admin/users/{role?}', [\App\Http\Controllers\Dev\StudentPreviewController::class, 'admin'])
        ->defaults('page', 'users')
        ->whereIn('role', ['mahasiswa', 'dosen', 'laboran'])
        ->name('dev.preview.admin.users');

    Route::get('/dev/preview/admin/monitoring-feed', [\App\Http\Controllers\Dev\StudentPreviewController::class, 'adminMonitoringFeed'])
        ->name('dev.preview.admin.monitoring-feed');

    Route::get('/dev/preview/admin/ai/conversations/{conversation}', [\App\Http\Controllers\Dev\StudentPreviewController::class, 'adminAiConversation'])
        ->whereNumber('conversation')
        ->name('dev.preview.admin.ai.conversations.show');

    Route::get('/dev/preview/admin/{page?}', [\App\Http\Controllers\Dev\StudentPreviewController::class, 'admin'])
        ->where('page', 'dashboard|users|ai|mesin|monitoring|atur-absensi|fingerprint|log-tap|ruangan|pengajuan|petugas|pengumuman|saran|antrian|pengaturan|kalender')
        ->name('dev.preview.admin');
}
