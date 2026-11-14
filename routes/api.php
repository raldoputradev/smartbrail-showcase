<?php

use App\Http\Controllers\Api\MesinAbsensiController;
use App\Http\Controllers\Api\MesinFingerprintController;
use App\Http\Controllers\Api\MesinHeartbeatController;
use App\Http\Controllers\Api\MobileCaptchaController;
use App\Http\Controllers\Api\MobileAuthController;
use App\Http\Controllers\Api\MobileDataController;
use App\Http\Controllers\Api\MobilePblTeamController;
use App\Http\Controllers\HelpdeskChatController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Endpoint Mesin IoT (ESP32)
|--------------------------------------------------------------------------
| Wajib kirim header: X-API-KEY: <api_key_mesin>
*/
Route::prefix('mesin')->group(function () {
    Route::match(['get', 'post'], '/heartbeat', [MesinHeartbeatController::class, 'ping'])
        ->middleware('mesin.auth');

    // Sync template mesin absensi.
    Route::middleware('mesin.auth')->group(function () {
        Route::get('/fingerprint/sync', [MesinFingerprintController::class, 'sync']);
        Route::get('/fingerprint/sync-status', [MesinFingerprintController::class, 'syncStatus']);
    });

    Route::middleware('mesin.auth:absensi')->group(function () {
        Route::post('/tap', [MesinAbsensiController::class, 'tap']);
        Route::get('/fingerprint/resolve', [MesinFingerprintController::class, 'resolve']);
        Route::get('/fingerprint/mahasiswa', [MesinFingerprintController::class, 'mahasiswa']);
        Route::post('/fingerprint/enroll', [MesinFingerprintController::class, 'enrollConfirm']);
        Route::delete('/fingerprint/{finger_id}', [MesinFingerprintController::class, 'deleteEnroll'])
            ->whereNumber('finger_id');
    });

});

/*
|--------------------------------------------------------------------------
| Endpoint Mobile (Flutter)
|--------------------------------------------------------------------------
*/
Route::prefix('mobile')->group(function () {
    Route::get('/config', [MobileAuthController::class, 'config']);
    Route::get('/captcha', [MobileCaptchaController::class, 'issue']);

    Route::post('/login', [MobileAuthController::class, 'login']);
    Route::post('/account-email', [MobileAuthController::class, 'submitAccountEmail']);
    Route::post('/account-email/verify', [MobileAuthController::class, 'verifyAccountEmail']);
    Route::post('/account-email/resend', [MobileAuthController::class, 'resendAccountVerification']);

    // Registrasi mahasiswa via Flutter (tanpa auth, OTP via email).
    Route::post('/register', [MobileAuthController::class, 'register']);
    Route::post('/register/verify', [MobileAuthController::class, 'verifyRegistration']);
    Route::post('/register/resend', [MobileAuthController::class, 'resendRegistrationOtp']);
    Route::post('/register/resume', [MobileAuthController::class, 'resumeRegistration']);
    Route::post('/register/cancel', [MobileAuthController::class, 'cancelRegistration']);
    Route::post('/forgot-password', [MobileAuthController::class, 'requestPasswordReset']);
    Route::post('/forgot-password/verify', [MobileAuthController::class, 'verifyPasswordReset']);
    Route::post('/forgot-password/reset', [MobileAuthController::class, 'resetPassword']);
    Route::post('/forgot-password/cancel', [MobileAuthController::class, 'cancelPasswordReset']);

    // CS AI publik (guest + login) — satu endpoint, tanpa wajib Bearer token.
    Route::post('/helpdesk/chat', HelpdeskChatController::class)
        ->middleware('throttle:30,1');

    Route::middleware('mobile.auth')->group(function () {
        Route::get('/me', [MobileAuthController::class, 'me']);
        Route::post('/me/account-email', [MobileAuthController::class, 'submitMyAccountEmail']);
        Route::post('/me/account-email/verify', [MobileAuthController::class, 'verifyMyAccountEmail']);
        Route::post('/me/account-email/resend', [MobileAuthController::class, 'resendMyAccountVerification']);
        Route::post('/me/profile', [MobileAuthController::class, 'updateProfile']);
        Route::post('/me/photo', [MobileAuthController::class, 'uploadProfilePhoto']);
        Route::post('/me/photo/delete', [MobileAuthController::class, 'deleteProfilePhoto']);
        Route::post('/logout', [MobileAuthController::class, 'logout']);
        Route::post('/me/push-token', [MobileAuthController::class, 'storePushToken']);

        Route::get('/me/absensi/snapshot', [MobileDataController::class, 'absensiSnapshot']);
        Route::get('/me/absensi', [MobileDataController::class, 'absensi']);
        Route::get('/announcements', [MobileDataController::class, 'announcements']);
        Route::get('/notifications', [MobileDataController::class, 'notificationsFeed']);
        Route::post('/announcements/read-all', [MobileDataController::class, 'markAnnouncementsReadAll']);
        Route::post('/announcements/{announcement}/read', [MobileDataController::class, 'markAnnouncementRead']);
        Route::get('/suggestions/replies', [MobileDataController::class, 'suggestionReplies']);
        Route::post('/suggestions/replies/read-all', [MobileDataController::class, 'markAllSuggestionRepliesRead']);
        Route::post('/suggestions/{suggestion}/read-reply', [MobileDataController::class, 'markSuggestionReplyRead']);
        Route::post('/suggestions', [MobileDataController::class, 'storeSuggestion']);

        Route::get('/pbl/projects', [MobilePblTeamController::class, 'projects']);
        Route::post('/me/pbl/join', [MobilePblTeamController::class, 'join']);
        Route::get('/me/pbl-team', [MobilePblTeamController::class, 'show']);
        Route::post('/me/pbl-team/anggota', [MobilePblTeamController::class, 'storeAnggota']);
        Route::delete('/me/pbl-team/anggota/{member}', [MobilePblTeamController::class, 'destroyAnggota']);
    });

    // Endpoint admin-rekap untuk Flutter (tetap memerlukan token, role dosen/laboran).
    Route::middleware('mobile.auth')->get('/logs', [MesinAbsensiController::class, 'mobileLogs']);
});
