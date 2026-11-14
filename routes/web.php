<?php

use App\Http\Controllers\AbsensiManagementController;
use App\Http\Controllers\ActivationController;
use App\Http\Controllers\Admin\AccountImportController;
use App\Http\Controllers\Admin\AdminAiController;
use App\Http\Controllers\Admin\PblProjectController;
use App\Http\Controllers\Admin\SuggestionController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AdminLogController;
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
use App\Http\Controllers\RolePermissionController;
use App\Http\Controllers\SessionGuardController;
use App\Http\Controllers\UserProfileController;
use Illuminate\Support\Facades\Route;

// Landing page publik (pengenalan project) — URL bersih di root domain.
Route::get('/', [LandingController::class, 'index'])->name('beranda');
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
    Route::get('/register', [AuthController::class, 'showAuthPage'])->name('register');
    Route::get('/register/verify', [AuthController::class, 'resumeRegistrationVerification'])
        ->middleware('signed')
        ->name('register.verify');

    Route::post('/register', [AuthController::class, 'register'])->name('register.post');
    Route::post('/register/resume', [AuthController::class, 'startRegistrationVerification'])
        ->middleware('throttle:8,1')
        ->name('register.resume.post');

    Route::post('/verify-registration', [AuthController::class, 'verifyRegistration'])->name('verify-registration.post');
    Route::post('/resend-verification', [AuthController::class, 'resendVerification'])->name('resend-verification.post');
    Route::post('/cancel-registration-verification', [AuthController::class, 'cancelRegistrationVerification'])->name('cancel-registration-verification.post');

    Route::get('/forgot-password', [AuthController::class, 'showAuthPage'])->name('forgot-password');
    Route::post('/forgot-password', [OTPController::class, 'sendOtp'])->name('forgot-password.post');
    Route::get('/verify-otp', [AuthController::class, 'showAuthPage'])->name('verify-otp');
    Route::post('/verify-otp', [OTPController::class, 'verifyOtp'])->name('verify-otp.post');
    Route::post('/resend-otp', [OTPController::class, 'resendOtp'])->name('resend-otp.post');
    Route::get('/reset-password', [AuthController::class, 'showAuthPage'])->name('reset-password');
    Route::post('/reset-password', [OTPController::class, 'resetPassword'])->name('reset-password.post');
    Route::post('/cancel-password-reset', [OTPController::class, 'cancelPasswordReset'])->name('cancel-password-reset.post');
});

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
Route::post('/account-email', [AuthController::class, 'submitAccountEmail'])->name('account-email.post');
Route::post('/verify-account-email', [AuthController::class, 'verifyAccountEmail'])->name('verify-account-email.post');
Route::post('/resend-account-verification', [AuthController::class, 'resendAccountVerification'])->name('resend-account-verification.post');
Route::post('/cancel-account-verification', [AuthController::class, 'cancelAccountVerification'])->name('cancel-account-verification.post');

Route::middleware(['auth', 'approved', 'no.back'])->group(function () {
    Route::get('/dashboard', function () {
        $user = auth()->user();

        return match ($user->role) {
            'laboran' => redirect('/admin/dashboard'),
            'dosen' => redirect('/dosen/dashboard'),
            'mahasiswa' => redirect()->route('mahasiswa.gate'),
            default => redirect()->route('login'),
        };
    })->name('dashboard');

    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/session/ping', [SessionGuardController::class, 'ping'])->name('session.ping');
    Route::get('/profiles/{user}', [UserProfileController::class, 'show'])->name('profiles.show');
    Route::post('/profile/photo', [UserProfileController::class, 'updateOwnPhoto'])->name('profiles.photo.update');
    Route::delete('/profile/photo', [UserProfileController::class, 'destroyOwnPhoto'])->name('profiles.photo.destroy');

    // Notifikasi pengumuman (admin / dosen)
    Route::prefix('notifications')->group(function () {
        Route::get('/feed', [NotificationController::class, 'feed'])->name('notifications.feed');
        Route::post('/announcements/{announcement}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
        Route::post('/announcements/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.readAll');
        Route::post('/ops/dismiss', [NotificationController::class, 'dismissOps'])->name('notifications.ops.dismiss');
    });

    Route::middleware('role:laboran')->prefix('admin')->group(function () {
        Route::get('/dashboard', [AdminController::class, 'dashboard'])->middleware('perm:dashboard,view')->name('admin.dashboard');
        Route::get('/dashboard/data', [AdminController::class, 'dashboardData'])->middleware('perm:dashboard,view')->name('admin.dashboard.data');
        Route::get('/users', [AdminController::class, 'users'])->middleware('perm:users,view')->name('admin.users');
        Route::post('/users', [AdminController::class, 'storeUser'])->middleware('perm:users,create')->name('admin.users.store');
        Route::put('/users/{user}', [AdminController::class, 'updateUser'])->middleware('perm:users,update')->name('admin.users.update');
        Route::delete('/users/{user}', [AdminController::class, 'destroyUser'])->middleware('perm:users,delete')->name('admin.users.destroy');

        // Import akun (hak = Tambah User). Kode aktivasi hanya di kartu sekali unduh.
        Route::middleware('perm:users,create')->prefix('users/import')->group(function () {
            Route::get('/', [AccountImportController::class, 'index'])->name('admin.users.import');
            Route::get('/template/{type}.{format}', [AccountImportController::class, 'template'])
                ->whereIn('type', ['mahasiswa', 'dosen', 'laboran'])
                ->whereIn('format', ['csv', 'xlsx'])
                ->name('admin.users.import.template');
            Route::post('/preview', [AccountImportController::class, 'preview'])->middleware('throttle:20,1')->name('admin.users.import.preview');
            Route::get('/{token}', [AccountImportController::class, 'show'])->where('token', '[A-Za-z0-9]{40}')->name('admin.users.import.show');
            Route::post('/{token}/apply', [AccountImportController::class, 'apply'])->where('token', '[A-Za-z0-9]{40}')->name('admin.users.import.apply');
            Route::get('/{token}/laporan.csv', [AccountImportController::class, 'report'])->where('token', '[A-Za-z0-9]{40}')->name('admin.users.import.report');
            Route::post('/{token}/kartu', [AccountImportController::class, 'cards'])->where('token', '[A-Za-z0-9]{40}')->name('admin.users.import.cards');
        });
        Route::post('/users/{user}/activation-code', [AccountImportController::class, 'regenerateCode'])
            ->middleware(['perm:users,create', 'throttle:20,1'])
            ->name('admin.users.activation-code');

        Route::get('/attendance', [AbsensiManagementController::class, 'rekap'])->middleware('perm:attendance,view')->name('admin.attendance');
        Route::get('/attendance/snapshot', [AbsensiManagementController::class, 'snapshot'])->middleware('perm:attendance,view')->name('admin.attendance.snapshot');
        Route::get('/attendance/export', [AbsensiManagementController::class, 'exportRekapExcel'])->middleware('perm:attendance,view')->name('admin.attendance.export');
        Route::get('/attendance/print', [AbsensiManagementController::class, 'printRekap'])->middleware('perm:attendance,view')->name('admin.attendance.print');
        Route::get('/absensi/pengaturan', [AbsensiManagementController::class, 'settings'])->middleware('perm:settings,view')->name('admin.absensi.settings');
        Route::post('/absensi/sesi', [AbsensiManagementController::class, 'updateSesi'])->middleware('perm:settings,update')->name('admin.absensi.sesi');
        Route::post('/absensi/kebijakan', [AbsensiManagementController::class, 'updateKebijakan'])->middleware('perm:settings,update')->name('admin.absensi.kebijakan');
        Route::post('/absensi/tutup-sesi', [AbsensiManagementController::class, 'runCloseSessions'])->middleware('perm:settings,update')->name('admin.absensi.tutup-sesi');
        Route::post('/absensi/generate-alfa', [AbsensiManagementController::class, 'runGenerateAlfa'])->middleware('perm:settings,update')->name('admin.absensi.generate-alfa');
        Route::post('/absensi/libur', [AbsensiManagementController::class, 'storeLibur'])->middleware('perm:settings,update')->name('admin.absensi.libur');
        Route::delete('/absensi/libur/{libur}', [AbsensiManagementController::class, 'destroyLibur'])->middleware('perm:settings,update')->name('admin.absensi.libur.destroy');
        Route::post('/absensi/prodi', [AbsensiManagementController::class, 'storeProdi'])->middleware('perm:settings,update')->name('admin.absensi.prodi');
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

        Route::get('/fingerprint', [AdminController::class, 'fingerprint'])->middleware('perm:fingerprint,view')->name('admin.fingerprint');
        Route::get('/fingerprint/snapshot', [AdminController::class, 'fingerprintSnapshot'])->middleware('perm:fingerprint,view')->name('admin.fingerprint.snapshot');
        Route::get('/monitoring', [AdminController::class, 'monitoring'])->middleware('perm:monitoring,view')->name('admin.monitoring');
        Route::get('/monitoring/feed', [AdminController::class, 'monitoringFeed'])->middleware('perm:monitoring,view')->name('admin.monitoring.feed');
        Route::get('/settings', [AdminController::class, 'settings'])->middleware('perm:settings,view')->name('admin.settings');
        Route::post('/settings/registrasi', [AdminController::class, 'updateRegistrationSettings'])->middleware('perm:settings,update')->name('admin.settings.registration');
        Route::get('/reports', [AdminController::class, 'reports'])->middleware('perm:reports,view')->name('admin.reports');
        Route::get('/reports/export/excel', [AdminController::class, 'exportReportExcel'])->middleware('perm:reports,view')->name('admin.reports.export.excel');
        Route::get('/reports/export/word', [AdminController::class, 'exportReportWord'])->middleware('perm:reports,view')->name('admin.reports.export.word');
        Route::get('/reports/print', [AdminController::class, 'printReport'])->middleware('perm:reports,view')->name('admin.reports.print');
        Route::get('/reports/student/{user}/detail', [AdminController::class, 'periodicStudentDetail'])->middleware('perm:reports,view')->name('admin.reports.student-detail');
        Route::get('/notifications', [AdminController::class, 'notifications'])->middleware('perm:notifications,view')->name('admin.notifications');

        Route::get('/mesin', [MesinIotController::class, 'index'])->middleware('perm:mesin,view')->name('admin.mesin.index');
        Route::post('/mesin', [MesinIotController::class, 'store'])->middleware('perm:mesin,create')->name('admin.mesin.store');
        Route::put('/mesin/{mesin}', [MesinIotController::class, 'update'])->middleware('perm:mesin,update')->name('admin.mesin.update');
        Route::post('/mesin/{mesin}/toggle', [MesinIotController::class, 'toggleActive'])->middleware('perm:mesin,update')->name('admin.mesin.toggle');
        Route::post('/mesin/{mesin}/regenerate', [MesinIotController::class, 'regenerate'])->middleware('perm:mesin,update')->name('admin.mesin.regenerate');
        Route::delete('/mesin/{mesin}', [MesinIotController::class, 'destroy'])->middleware('perm:mesin,delete')->name('admin.mesin.destroy');

        Route::get('/log/absensi', [AdminLogController::class, 'absensi'])->middleware('perm:log_absensi,view')->name('admin.log.absensi');
        Route::get('/log/absensi/snapshot', [AdminLogController::class, 'snapshot'])->middleware('perm:log_absensi,view')->name('admin.log.absensi.snapshot');
        Route::get('/announcements/{announcement}/json', [AnnouncementController::class, 'showJson'])->middleware('perm:announcements,view')->name('admin.announcements.json');
        Route::get('/announcements', [AnnouncementController::class, 'index'])->middleware('perm:announcements,view')->name('admin.announcements.index');
        Route::post('/announcements', [AnnouncementController::class, 'store'])->middleware('perm:announcements,create')->name('admin.announcements.store');
        Route::put('/announcements/{announcement}', [AnnouncementController::class, 'update'])->middleware('perm:announcements,update')->name('admin.announcements.update');
        Route::post('/announcements/{announcement}/toggle', [AnnouncementController::class, 'toggle'])->middleware('perm:announcements,update')->name('admin.announcements.toggle');
        Route::delete('/announcements/{announcement}', [AnnouncementController::class, 'destroy'])->middleware('perm:announcements,delete')->name('admin.announcements.destroy');

        Route::get('/role-access', [RolePermissionController::class, 'index'])->middleware('perm:role_access,view')->name('admin.role.index');
        Route::post('/role-access', [RolePermissionController::class, 'update'])->middleware('perm:role_access,update')->name('admin.role.update');
        Route::post('/role-access/reset', [RolePermissionController::class, 'resetDefaults'])->middleware('perm:role_access,update')->name('admin.role.reset');

        Route::post('/fingerprint/{user}/assign', [AdminController::class, 'assignFingerId'])->middleware('perm:fingerprint,update')->name('admin.fingerprint.assign');
        Route::delete('/fingerprint/{user}/reset', [AdminController::class, 'resetFingerId'])->middleware('perm:fingerprint,delete')->name('admin.fingerprint.reset');
        Route::post('/fingerprint/{user}/clear-enroll', [AdminController::class, 'clearFingerprintEnroll'])->middleware('perm:fingerprint,update')->name('admin.fingerprint.clear-enroll');
        Route::post('/fingerprint/backfill-shift', [AdminController::class, 'backfillMahasiswaShift'])->middleware('perm:fingerprint,update')->name('admin.fingerprint.backfill-shift');
        Route::post('/fingerprint/sync-mode', [AdminController::class, 'updateFingerprintSyncMode'])->middleware('perm:fingerprint,update')->name('admin.fingerprint.sync-mode');
        Route::post('/fingerprint/sync-distribute', [AdminController::class, 'triggerFingerprintSyncBurst'])->middleware('perm:fingerprint,update')->name('admin.fingerprint.sync-distribute');

        // Saran & masukan dari landing publik.
        Route::get('/suggestions', [SuggestionController::class, 'index'])->middleware('perm:suggestions,view')->name('admin.suggestions.index');
        Route::get('/suggestions/feed', [SuggestionController::class, 'feed'])->middleware('perm:suggestions,view')->name('admin.suggestions.feed');
        Route::patch('/suggestions/{suggestion}', [SuggestionController::class, 'updateStatus'])->middleware('perm:suggestions,update')->name('admin.suggestions.update');
        Route::post('/suggestions/{suggestion}/reply', [SuggestionController::class, 'reply'])->middleware('perm:suggestions,update')->name('admin.suggestions.reply');
        Route::delete('/suggestions/{suggestion}/reply', [SuggestionController::class, 'clearReply'])->middleware('perm:suggestions,update')->name('admin.suggestions.reply.clear');
        Route::delete('/suggestions/{suggestion}', [SuggestionController::class, 'destroy'])->middleware('perm:suggestions,delete')->name('admin.suggestions.destroy');

        Route::post('/users/{user}/approve', [AdminController::class, 'approveUser'])->middleware('perm:users,update')->name('admin.users.approve');
        Route::post('/users/bulk-approve', [AdminController::class, 'bulkApproveUsers'])->middleware('perm:users,update')->name('admin.users.bulk-approve');
        Route::post('/users/{user}/reject', [AdminController::class, 'rejectUser'])->middleware('perm:users,update')->name('admin.users.reject');
        Route::post('/users/{user}/toggle-active', [AdminController::class, 'toggleUserActive'])->middleware('perm:users,update')->name('admin.users.toggle-active');
        Route::post('/users/{user}/unlock-login', [AdminController::class, 'unlockUserLogin'])->middleware('perm:users,update')->name('admin.users.unlock-login');

        Route::get('/ai', [AdminAiController::class, 'index'])->middleware('perm:ai_assistant,view')->name('admin.ai');
        Route::get('/ai/conversations', [AdminAiController::class, 'conversations'])->middleware('perm:ai_assistant,view')->name('admin.ai.conversations');
        Route::post('/ai/conversations', [AdminAiController::class, 'storeConversation'])->middleware(['perm:ai_assistant,view', 'throttle:30,1'])->name('admin.ai.conversations.store');
        Route::get('/ai/conversations/{conversation}', [AdminAiController::class, 'showConversation'])->middleware('perm:ai_assistant,view')->name('admin.ai.conversations.show');
        Route::delete('/ai/conversations/{conversation}', [AdminAiController::class, 'destroyConversation'])->middleware(['perm:ai_assistant,view', 'throttle:30,1'])->name('admin.ai.conversations.destroy');
        Route::post('/ai/chat', [AdminAiController::class, 'chat'])->middleware(['perm:ai_assistant,view', 'throttle:30,1'])->name('admin.ai.chat');
        Route::post('/ai/chat/stream', [AdminAiController::class, 'stream'])->middleware(['perm:ai_assistant,view', 'throttle:30,1'])->name('admin.ai.chat.stream');
        Route::post('/ai/actions/{proposal}/confirm', [AdminAiController::class, 'confirm'])->middleware(['perm:ai_assistant,view', 'throttle:40,1'])->name('admin.ai.confirm');
        Route::post('/ai/actions/{proposal}/cancel', [AdminAiController::class, 'cancel'])->middleware(['perm:ai_assistant,view', 'throttle:40,1'])->name('admin.ai.cancel');
    });

    Route::middleware('role:dosen')->prefix('dosen')->group(function () {
        Route::get('/dashboard', [DosenController::class, 'dashboard'])->middleware('perm:dashboard,view')->name('dosen.dashboard');
        Route::get('/dashboard/data', [DosenController::class, 'dashboardData'])->middleware('perm:dashboard,view')->name('dosen.dashboard.data');
        Route::get('/ai', [AdminAiController::class, 'index'])->middleware('perm:ai_assistant,view')->name('dosen.ai');
        Route::get('/ai/conversations', [AdminAiController::class, 'conversations'])->middleware('perm:ai_assistant,view')->name('dosen.ai.conversations');
        Route::get('/ai/conversations/{conversation}', [AdminAiController::class, 'showConversation'])->middleware('perm:ai_assistant,view')->name('dosen.ai.conversations.show');
        Route::delete('/ai/conversations/{conversation}', [AdminAiController::class, 'destroyConversation'])->middleware(['perm:ai_assistant,view', 'throttle:30,1'])->name('dosen.ai.conversations.destroy');
        Route::post('/ai/chat/stream', [AdminAiController::class, 'stream'])->middleware(['perm:ai_assistant,view', 'throttle:30,1'])->name('dosen.ai.chat.stream');
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
        Route::post('/pbl/{pbl}/anggota', [DosenPblController::class, 'storeAnggota'])->middleware('perm:dosen_pbl,create')->name('dosen.pbl.anggota.store');
        Route::post('/pbl/{pbl}/ketua', [DosenPblController::class, 'storeKetua'])->middleware('perm:dosen_pbl,create')->name('dosen.pbl.ketua.store');
        Route::post('/pbl/{pbl}/wakil', [DosenPblController::class, 'storeWakil'])->middleware('perm:dosen_pbl,create')->name('dosen.pbl.wakil.store');
        Route::post('/pbl/{pbl}/clear-role', [DosenPblController::class, 'clearTeamRole'])->middleware('perm:dosen_pbl,delete')->name('dosen.pbl.clear-role');
        Route::delete('/pbl/{pbl}/members/{user}', [DosenPblController::class, 'destroyMember'])->middleware('perm:dosen_pbl,delete')->name('dosen.pbl.members.destroy');
        Route::get('/announcements', [DosenController::class, 'announcements'])->middleware('perm:announcements,view')->name('dosen.announcements');
        Route::get('/profile', [DosenController::class, 'profile'])->middleware('perm:dosen_profile,view')->name('dosen.profile');
        Route::get('/saran', [FeedbackController::class, 'index'])->name('dosen.feedback.index');
        Route::post('/saran', [FeedbackController::class, 'store'])
            ->middleware('throttle:12,1')
            ->name('dosen.feedback.store');
        Route::post('/saran/{suggestion}/baca', [FeedbackController::class, 'markReplyRead'])->name('dosen.feedback.read');
    });

    // Mahasiswa: tidak ada portal web — hanya aplikasi mobile (redirect /app ke halaman info).
    Route::get('/app-nonaktif', fn () => view('mahasiswa.web-disabled'))->name('mahasiswa.gate');
    Route::middleware(['role:mahasiswa'])->group(function () {
        Route::redirect('/app', '/app-nonaktif');
        Route::redirect('/app/{path}', '/app-nonaktif')->where('path', '.*');
    });
});
