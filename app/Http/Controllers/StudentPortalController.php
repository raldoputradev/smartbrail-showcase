<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Api\MobileDataController;
use App\Http\Controllers\Api\MobilePblTeamController;
use App\Models\Announcement;
use App\Models\ProgramStudi;
use App\Models\Ruangan;
use App\Models\RuanganIzin;
use App\Models\RuanganPengajuan;
use App\Models\Suggestion;
use App\Models\User;
use App\Rules\NotDisposableEmail;
use App\Rules\StrongPassword;
use App\Services\HelpdeskAiService;
use App\Support\AbsensiEffectiveSchedule;
use App\Support\AbsensiKebijakan;
use App\Support\AccountActivation;
use App\Support\AccountEmailVerification;
use App\Support\RuanganBookingService;
use App\Support\WebSessionGuard;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StudentPortalController extends Controller
{
    public function showChangePassword(Request $request): View|\Inertia\Response
    {
        /** @var User $user */
        $user = $request->user();
        $isMandatory = (bool) $user->must_change_password;

        return \Inertia\Inertia::render('Student/ChangePassword', array_merge(
            $this->sharedLayoutData($user),
            [
                'user' => $user,
                'isMandatory' => $isMandatory,
            ]
        ));
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $isMandatory = (bool) $user->must_change_password;

        $rules = [
            'password' => ['required', 'string', 'confirmed', new StrongPassword()],
        ];
        $messages = [
            'password.required' => 'Password baru wajib diisi.',
            'password.confirmed' => 'Konfirmasi password baru tidak cocok.',
        ];

        if (! $isMandatory) {
            $rules['current_password'] = ['required', 'string'];
            $messages['current_password.required'] = 'Password saat ini wajib diisi untuk verifikasi keamanan.';
        }

        $validated = $request->validate($rules, $messages);

        if (! $isMandatory && ! Hash::check((string) $validated['current_password'], (string) $user->password)) {
            return back()->withErrors([
                'current_password' => 'Password saat ini tidak sesuai.',
            ]);
        }

        if (Hash::check($validated['password'], (string) $user->password)) {
            return back()->withErrors([
                'password' => 'Password baru harus berbeda dari password yang sedang digunakan.',
            ]);
        }

        DB::transaction(function () use ($user, $validated, $request, $isMandatory) {
            $updates = [
                'password' => $validated['password'],
                'remember_token' => \Illuminate\Support\Str::random(60),
            ];

            if (Schema::hasColumn('users', 'must_change_password')) {
                $updates['must_change_password'] = false;
            }

            $user->forceFill($updates)->save();

            AccountActivation::log(
                $user,
                $user,
                $request,
                'activation_password_set',
                $isMandatory
                    ? 'Mahasiswa mengganti password sementara dengan password pribadi.'
                    : 'Mahasiswa memperbarui password pribadi secara mandiri.'
            );
        });

        WebSessionGuard::stampLogin($request, $user->fresh());

        $flashMessage = $isMandatory
            ? 'Password pribadi berhasil disimpan. Selamat datang di Portal Mahasiswa SIMALAS.'
            : 'Password akun Anda berhasil diperbarui.';

        return to_route('mahasiswa.beranda')
            ->with('success', $flashMessage);
    }

    public function dismissContactPrompt(Request $request): RedirectResponse
    {
        $user = $request->user();
        if ($user) {
            $request->session()->put('student_contact_prompt_dismissed_' . $user->id, true);
        }

        return back()->with('status', 'Pengingat kelengkapan kontak ditutup untuk sesi login ini.');
    }

    public function dashboard(Request $request): View|\Inertia\Response
    {
        $user = $request->user();
        $shift = $user->shift ?: 'pagi';
        $tz = 'Asia/Jakarta';
        $today = now($tz);
        $todayStr = $today->toDateString();

        $attendancePayload = $this->callMobile($request, MobileDataController::class, 'absensi', [
            'limit' => 30,
            'days' => 365,
        ]);

        $rawHarian = collect($attendancePayload['ringkasan_harian'] ?? []);
        $enrichedHarian = $rawHarian->map(fn (array $row) => $this->enrichAttendanceRow($row, $shift))->values();

        // Today's effective schedule & attendance record
        $todaySchedule = AbsensiEffectiveSchedule::resolve($todayStr, $shift);
        $todayRecord = $enrichedHarian->firstWhere('tanggal', $todayStr);

        if (! $todayRecord) {
            // Check if there is an AbsensiHarian row on today even if today wasn't in filtered ringkasan_harian
            $dbToday = \App\Models\AbsensiHarian::query()
                ->with(['masukLog', 'pulangLog'])
                ->where('user_id', $user->id)
                ->whereDate('tanggal', $todayStr)
                ->first();

            if ($dbToday) {
                $todayRecord = $this->enrichAttendanceRow([
                    'tanggal' => $todayStr,
                    'sesi' => $dbToday->sesi ?: $shift,
                    'status' => $dbToday->status,
                    'jam_masuk' => $dbToday->masukLog?->waktu_tap?->timezone($tz)->format('H:i'),
                    'jam_pulang' => $dbToday->pulangLog?->waktu_tap?->timezone($tz)->format('H:i'),
                    'menit_terlambat_masuk' => $dbToday->menit_terlambat_masuk,
                    'menit_terlambat_pulang' => $dbToday->menit_terlambat_pulang,
                    'menit_pulang_cepat' => $dbToday->menit_pulang_cepat ?? 0,
                    'pulang_cepat' => (bool) ($dbToday->pulang_cepat ?? false),
                    'pulang_lembur' => (bool) $dbToday->pulang_lembur,
                    'slot_lab_masuk' => $dbToday->slot_lab_masuk,
                    'lengkap_at' => optional($dbToday->lengkap_at)->toDateTimeString(),
                ], $shift);
            }
        }

        $todayAssessment = $todayRecord
            ? $todayRecord['penilaian']
            : AbsensiEffectiveSchedule::assessRecord($todaySchedule, []);

        // Ringkasan periode: basis = catatan harian pada hari terjadwal dalam rentang yang dipilih.
        $periodOptions = ['bulan_ini' => 'Bulan ini', 30 => '30 hari terakhir', 90 => '90 hari terakhir', 365 => '12 bulan terakhir'];
        $periodSel = (string) $request->query('periode', 'bulan_ini');
        if (! array_key_exists($periodSel, $periodOptions)) {
            $periodSel = 'bulan_ini';
        }
        $periodStart = $periodSel === 'bulan_ini'
            ? $today->copy()->startOfMonth()->toDateString()
            : $today->copy()->subDays((int) $periodSel)->toDateString();
        $periodRows = $enrichedHarian->filter(fn (array $r) => $r['tanggal'] >= $periodStart)->values();

        $lengkapCount = $periodRows->where('status', 'lengkap')->count();
        $tidakLengkapCount = $periodRows->whereIn('status', ['tidak_lengkap', 'masuk', 'belum_lengkap'])->count();
        $alfaCount = $periodRows->where('status', 'alfa')->count();
        $catatanKetepatanCount = $periodRows->where('has_punctuality_note', true)->count();
        $terlambatCount = $periodRows->where('ketepatan_masuk_kode', 'terlambat')->count();
        $pulangCepatCount = $periodRows->where('ketepatan_pulang_kode', 'pulang_cepat')->count();

        $periodRangeLabel = Carbon::parse($periodStart)->translatedFormat('d M Y') . ' – ' . $today->translatedFormat('d M Y');

        // Calendar & Academic Agenda for selected/current month
        $monthParam = (string) $request->query('bulan', $today->format('Y-m'));
        if (! preg_match('/^\d{4}-\d{2}$/', $monthParam)) {
            $monthParam = $today->format('Y-m');
        }

        // Smart BRAIL: data ruangan mahasiswa untuk beranda (2 query total, tanpa per-tanggal).
        $tim = RuanganBookingService::timMahasiswa($user);
        $bms = $this->ruanganDashboardData($user, $tim, $today, $monthParam);

        $calendarData = $this->buildMonthCalendar($monthParam, $shift, $enrichedHarian, $todayStr, $bms['kalender_ruangan']);
        $scheduleMeta = $this->scheduleMeta($shift, $todayStr);
        $todayState = $this->todayState($todaySchedule, $todayRecord, $today, $scheduleMeta);

        $hasVerifiedEmail = filled($user->email) && $user->email_verified_at !== null;
        $hasPhone = filled($user->no_hp);
        $showContactPrompt = (! $hasVerifiedEmail || ! $hasPhone)
            && ! $request->session()->get('student_contact_prompt_dismissed_' . $user->id, false);

        $prodiName = $user->programStudi?->nama
            ?? (filled($user->prodi ?? null) ? strtoupper((string) $user->prodi) : null);

        return \Inertia\Inertia::render('Student/Dashboard', array_merge(
            $this->sharedLayoutData($user),
            [
                'user' => $user,
                'prodiName' => $prodiName,
                'attendance' => array_merge($attendancePayload, [
                    'ringkasan_harian' => $enrichedHarian->all(),
                ]),
                'todaySchedule' => $todaySchedule,
                'todayRecord' => $todayRecord,
                'todayAssessment' => $todayAssessment,
                'periodSummary' => [
                    'lengkap' => $lengkapCount,
                    'tidak_lengkap' => $tidakLengkapCount,
                    'alfa' => $alfaCount,
                    'catatan_ketepatan' => $catatanKetepatanCount,
                    'terlambat' => $terlambatCount,
                    'pulang_cepat' => $pulangCepatCount,
                    'total_sesi' => $periodRows->count(),
                    'range_label' => $periodRangeLabel,
                    'kebijakan_label' => AbsensiKebijakan::labelMode(),
                    'days' => $periodSel,
                    'options' => $periodOptions,
                ],
                'recentRows' => $enrichedHarian->take(5)->values()->all(),
                'calendar' => $calendarData,
                'scheduleMeta' => $scheduleMeta,
                'todayState' => $todayState,
                'tim' => $tim,
                'bms' => $bms,
                'showContactPrompt' => $showContactPrompt,
                'hasVerifiedEmail' => $hasVerifiedEmail,
                'hasPhone' => $hasPhone,
            ]
        ));
    }

    /**
     * Data kalender satu bulan (JSON) untuk navigasi kalender tanpa memuat ulang halaman.
     */
    public function calendarMonth(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'bulan' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
        ]);

        $user = $request->user();
        $shift = $user->shift ?: 'pagi';
        $todayStr = now('Asia/Jakarta')->toDateString();

        return response()->json([
            'success' => true,
            'calendar' => $this->buildMonthCalendar(
                $validated['bulan'],
                $shift,
                $this->loadEnrichedHarian($request, $shift),
                $todayStr,
                $this->ruanganCalendarEvents($user, $validated['bulan'])
            ),
        ]);
    }

    public function calendarPage(Request $request): View|\Inertia\Response
    {
        $user = $request->user();
        $shift = $user->shift ?: 'pagi';
        $today = now('Asia/Jakarta');
        $todayStr = $today->toDateString();

        $monthParam = (string) $request->query('bulan', $today->format('Y-m'));
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $monthParam)) {
            $monthParam = $today->format('Y-m');
        }

        return \Inertia\Inertia::render('Student/Calendar', array_merge(
            $this->sharedLayoutData($user),
            [
                'user' => $user,
                'calendar' => $this->buildMonthCalendar($monthParam, $shift, $this->loadEnrichedHarian($request, $shift), $todayStr, $this->ruanganCalendarEvents($user, $monthParam)),
                'scheduleMeta' => $this->scheduleMeta($shift, $todayStr),
            ]
        ));
    }

    public function guide(Request $request): \Inertia\Response
    {
        $user = $request->user();

        return \Inertia\Inertia::render('Student/Guide', array_merge(
            $this->sharedLayoutData($user),
            [
                'user' => $user,
                'scheduleMeta' => $this->scheduleMeta($user->shift ?: 'pagi', now('Asia/Jakarta')->toDateString()),
            ]
        ));
    }

    /** Halaman placeholder fitur yang masih dalam pengembangan. */
    public function comingSoon(Request $request): \Inertia\Response
    {
        $user = $request->user();

        return \Inertia\Inertia::render('Student/ComingSoon', array_merge(
            $this->sharedLayoutData($user),
            ['user' => $user]
        ));
    }

    /**
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function loadEnrichedHarian(Request $request, string $shift)
    {
        $payload = $this->callMobile($request, MobileDataController::class, 'absensi', [
            'limit' => 30,
            'days' => 365,
        ]);

        return collect($payload['ringkasan_harian'] ?? [])
            ->map(fn (array $row) => $this->enrichAttendanceRow($row, $shift))
            ->values();
    }

    public function attendance(Request $request): View|\Inertia\Response
    {
        $user = $request->user();
        $shift = $user->shift ?: 'pagi';

        $attendancePayload = $this->callMobile($request, MobileDataController::class, 'absensi', [
            'limit' => 200,
            'days' => 365,
        ]);

        $allRows = collect($attendancePayload['ringkasan_harian'] ?? [])
            ->map(fn (array $row) => $this->enrichAttendanceRow($row, $shift))
            ->values();

        $availableMonths = $allRows
            ->map(fn (array $r) => substr($r['tanggal'], 0, 7))
            ->unique()
            ->values()
            ->map(fn (string $ym) => [
                'value' => $ym,
                'label' => Carbon::createFromFormat('Y-m', $ym)->translatedFormat('F Y'),
            ])
            ->all();

        $filterStatus = (string) $request->query('status', 'semua');
        $filterBulan = (string) $request->query('bulan', 'semua');

        $filteredRows = $allRows->filter(function (array $row) use ($filterStatus, $filterBulan) {
            if ($filterBulan !== 'semua' && preg_match('/^\d{4}-\d{2}$/', $filterBulan)) {
                if (! str_starts_with($row['tanggal'], $filterBulan)) {
                    return false;
                }
            }

            return match ($filterStatus) {
                'lengkap' => $row['status'] === 'lengkap',
                'tidak_lengkap' => in_array($row['status'], ['tidak_lengkap', 'masuk', 'belum_lengkap'], true),
                'alfa' => $row['status'] === 'alfa',
                'catatan_waktu' => (bool) $row['has_punctuality_note'],
                default => true,
            };
        })->values();

        $oldestDate = $allRows->last()['tanggal'] ?? null;
        $newestDate = $allRows->first()['tanggal'] ?? null;
        $periodRangeLabel = ($oldestDate && $newestDate)
            ? Carbon::parse($oldestDate)->translatedFormat('d M Y') . ' – ' . Carbon::parse($newestDate)->translatedFormat('d M Y')
            : '365 hari terakhir';

        $summary = [
            'lengkap' => $allRows->where('status', 'lengkap')->count(),
            'tidak_lengkap' => $allRows->whereIn('status', ['tidak_lengkap', 'masuk', 'belum_lengkap'])->count(),
            'alfa' => $allRows->where('status', 'alfa')->count(),
            'catatan_waktu' => $allRows->where('has_punctuality_note', true)->count(),
            'total' => $allRows->count(),
            'range_label' => $periodRangeLabel,
            'kebijakan_label' => AbsensiKebijakan::labelMode(),
        ];

        return \Inertia\Inertia::render('Student/Attendance', array_merge(
            $this->sharedLayoutData($user),
            [
                'user' => $user,
                'attendance' => $attendancePayload,
                'rows' => $filteredRows,
                'summary' => $summary,
                'availableMonths' => $availableMonths,
                'filterStatus' => $filterStatus,
                'filterBulan' => $filterBulan,
            ]
        ));
    }

    public function profile(Request $request): View|\Inertia\Response
    {
        $user = $request->user();
        $prodiName = $user->programStudi?->nama
            ?? (filled($user->prodi ?? null) ? strtoupper((string) $user->prodi) : null);

        $izinPins = \App\Models\RuanganIzin::with('ruangan:id,nama,kode')
            ->where('user_id', $user->id)
            ->where('status', \App\Models\RuanganIzin::STATUS_AKTIF)
            ->where('selesai', '>=', now())
            ->orderBy('mulai')
            ->get(['id', 'ruangan_id', 'mulai', 'selesai', 'akses_pin_enc'])
            ->map(fn (\App\Models\RuanganIzin $z) => [
                'ruangan' => $z->ruangan?->nama ?? '—',
                'mulai' => $z->mulai?->format('d M, H:i'),
                'selesai' => $z->selesai?->format('d M, H:i'),
                'pin' => $z->aksesPin(),
            ])
            ->filter(fn ($z) => filled($z['pin']))
            ->values();

        return \Inertia\Inertia::render('Student/Profile', array_merge(
            $this->sharedLayoutData($user),
            [
                'user' => $user,
                'prodiName' => $prodiName,
                'pendingEmailOtp' => filled($user->email) && $user->email_verified_at === null,
                'kartuAkses' => $user->rfid_uid,
                'izinPins' => $izinPins,
            ]
        ));
    }

    public function updateContact(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'email' => [
                'nullable',
                'string',
                'email',
                'max:255',
                new NotDisposableEmail(),
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'no_hp' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s()]{8,30}$/'],
        ], [
            'email.email' => 'Format alamat email tidak valid.',
            'email.unique' => 'Email sudah terdaftar pada akun lain.',
            'no_hp.regex' => 'Nomor HP hanya boleh berisi angka atau tanda + (8–30 karakter).',
        ]);

        $newEmail = isset($validated['email']) && trim((string) $validated['email']) !== ''
            ? AccountEmailVerification::normalizeEmail($validated['email'])
            : null;
        $newPhone = isset($validated['no_hp']) && trim((string) $validated['no_hp']) !== ''
            ? trim((string) $validated['no_hp'])
            : null;

        $oldEmail = AccountEmailVerification::normalizeEmail($user->email);
        $emailChanged = $newEmail !== ($oldEmail !== '' ? $oldEmail : null);

        if ($user->email_verified_at !== null && $emailChanged) {
            return to_route('mahasiswa.profile')->withErrors([
                'email' => 'Email sudah terverifikasi dan tidak dapat diubah. Hubungi laboran/admin jika ada kendala.',
            ]);
        }

        $oldPhone = filled($user->no_hp) ? trim((string) $user->no_hp) : null;
        if ($oldPhone !== null && $newPhone !== $oldPhone) {
            return to_route('mahasiswa.profile')->withErrors([
                'no_hp' => 'No. HP sudah tersimpan dan tidak dapat diubah. Hubungi laboran/admin jika ada kendala.',
            ]);
        }

        $updates = [
            'no_hp' => $newPhone,
        ];

        if ($emailChanged) {
            $updates['email'] = $newEmail;
            $updates['email_verified_at'] = null;
            if ($oldEmail !== '') {
                DB::table('email_verification_tokens')
                    ->where('email', $oldEmail)
                    ->where('type', AccountEmailVerification::TYPE)
                    ->delete();
            }
        }

        $user->forceFill($updates)->save();
        $user->refresh();

        $otpMessage = null;
        if ($newEmail !== null && $user->email_verified_at === null && $emailChanged) {
            try {
                $otpMessage = AccountEmailVerification::send($user);
            } catch (\Throwable $e) {
                return to_route('mahasiswa.profile')
                    ->with('success', 'Kontak berhasil disimpan.')
                    ->withErrors(['email' => $e->getMessage()]);
            }
        }

        return to_route('mahasiswa.profile')
            ->with('success', 'Data kontak berhasil diperbarui.' . ($otpMessage ? ' ' . $otpMessage : ''));
    }

    public function resendEmailOtp(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! filled($user->email)) {
            return to_route('mahasiswa.profile')->withErrors([
                'email' => 'Simpan alamat email terlebih dahulu sebelum meminta OTP.',
            ]);
        }

        if ($user->email_verified_at !== null) {
            return to_route('mahasiswa.profile')->with('success', 'Alamat email Anda sudah terverifikasi.');
        }

        try {
            $msg = AccountEmailVerification::send($user);
        } catch (\Throwable $e) {
            return to_route('mahasiswa.profile')->withErrors([
                'otp_code' => $e->getMessage(),
            ]);
        }

        return to_route('mahasiswa.profile')->with('success', $msg);
    }

    public function verifyEmailOtp(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $codeInput = (string) ($request->input('otp_code') ?? $request->input('otp') ?? '');
        $request->merge(['otp_code' => trim($codeInput)]);

        $validated = $request->validate([
            'otp_code' => ['required', 'digits:6'],
        ], [
            'otp_code.required' => 'Kode OTP 6 digit wajib diisi.',
            'otp_code.digits' => 'Kode OTP harus terdiri dari 6 angka.',
        ]);

        if (! filled($user->email)) {
            return to_route('mahasiswa.profile')->withErrors([
                'otp_code' => 'Alamat email belum diisi.',
            ]);
        }

        if ($user->email_verified_at !== null) {
            return to_route('mahasiswa.profile')->with('success', 'Alamat email sudah terverifikasi.');
        }

        $verifiedUser = AccountEmailVerification::verify((string) $user->email, (string) $validated['otp_code']);
        if (! $verifiedUser) {
            return to_route('mahasiswa.profile')->withErrors([
                'otp_code' => 'Kode OTP tidak valid atau sudah kedaluwarsa.',
            ]);
        }

        AccountActivation::log(
            $verifiedUser,
            $verifiedUser,
            $request,
            'activation_email_verified',
            'Email mahasiswa berhasil diverifikasi melalui OTP profil.'
        );

        return to_route('mahasiswa.profile')->with(
            'success',
            'Email berhasil diverifikasi. Sekarang email ini dapat digunakan untuk pemulihan lupa password.'
        );
    }

    public function team(Request $request): View|\Inertia\Response
    {
        $user = $request->user();
        $teamPayload = $this->callMobile($request, MobilePblTeamController::class, 'show');

        return \Inertia\Inertia::render('Student/Team', array_merge(
            $this->sharedLayoutData($user),
            [
                'user' => $user,
                'team' => $teamPayload['data'] ?? $teamPayload,
            ]
        ));
    }

    public function notifications(Request $request): View|JsonResponse
    {
        $user = $request->user();
        if ($request->expectsJson() && $request->routeIs('mahasiswa.notifications.feed')) {
            return $this->notificationsFeed($request);
        }

        $unreadOnly = $request->boolean('unread_only');
        $feed = $this->callMobile($request, MobileDataController::class, 'notificationsFeed', [
            'page' => max(1, (int) $request->query('page', 1)),
            'per_page' => (int) $request->query('per_page', 10),
            'unread_only' => $unreadOnly ? 1 : 0,
        ]);

        return view('student.notifications', array_merge(
            $this->sharedLayoutData($user),
            [
                'user' => $user,
                'notificationPage' => $feed,
                'unreadOnly' => $unreadOnly,
                'feed' => $feed,
                'announcements' => $this->callMobile($request, MobileDataController::class, 'announcements', ['limit' => 50]),
                'replies' => $this->callMobile($request, MobileDataController::class, 'suggestionReplies', ['limit' => 50]),
            ]
        ));
    }

    public function notificationsFeed(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $filter = (string) $request->query('filter', 'all');
        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(30, max(5, (int) $request->query('per_page', 12)));

        $state = $this->buildStudentNotifications($user, $filter, $page, $perPage);

        return response()->json([
            'success' => true,
            'unread_count' => $state['unread_count'],
            'filter' => $filter,
            'page' => $page,
            'has_more' => $state['has_more'],
            'items' => $state['items'],
        ]);
    }

    public function readAnnouncement(Request $request, Announcement $announcement): RedirectResponse|JsonResponse
    {
        $res = $this->callMobile($request, MobileDataController::class, 'markAnnouncementRead', [], $announcement);

        if ($request->expectsJson()) {
            $state = $this->buildStudentNotifications($request->user(), 'all', 1, 12);

            return response()->json([
                'success' => (bool) ($res['success'] ?? true),
                'unread_count' => $state['unread_count'],
            ]);
        }

        return back()->with('success', 'Pengumuman ditandai sudah dibaca.');
    }

    /** Tandai notifikasi keputusan pengajuan dibaca — read-state saja, audit utuh. */
    public function readOpsNotification(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'key' => 'required|string|max:60',
            'fp' => 'required|string|max:80',
        ]);
        abort_unless(
            str_starts_with($data['key'], 'pgj-') || str_starts_with($data['key'], 'pgjr-'),
            403
        );

        \App\Support\StaffNotificationService::dismiss($request->user(), $data['key'], $data['fp']);

        if ($request->expectsJson()) {
            $state = $this->buildStudentNotifications($request->user(), 'all', 1, 12);

            return response()->json(['success' => true, 'unread_count' => $state['unread_count']]);
        }

        return back();
    }

    public function readAllAnnouncements(Request $request): RedirectResponse|JsonResponse
    {
        $this->callMobile($request, MobileDataController::class, 'markAnnouncementsReadAll');
        $this->callMobile($request, MobileDataController::class, 'markAllSuggestionRepliesRead');

        // Notifikasi keputusan pengajuan juga ditandai — dismissal only, bukan audit.
        RuanganPengajuan::query()
            ->where(fn ($q) => $q->where('pengaju_id', $request->user()->id)
                ->orWhereJsonContains('peserta', $request->user()->id))
            ->whereNotNull('diputus_pada')
            ->get(['id', 'diputus_pada'])
            ->each(function (RuanganPengajuan $p) use ($request) {
                $key = 'pgj-' . $p->id;
                \App\Support\StaffNotificationService::dismiss(
                    $request->user(), $key,
                    \App\Support\StaffNotificationService::fingerprint(['id' => $key, 'count' => $p->diputus_pada->timestamp])
                );
            });

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'unread_count' => 0,
            ]);
        }

        return back()->with('success', 'Semua notifikasi ditandai sudah dibaca.');
    }

    public function suggestions(Request $request): View|\Inertia\Response
    {
        $user = $request->user();
        $base = Suggestion::query()->where('user_id', $user->id);

        $stats = [
            'total' => (clone $base)->count(),
            'baru' => (clone $base)->where('status', 'baru')->count(),
            'dibaca' => (clone $base)->where('status', 'dibaca')->count(),
            'ditanggapi' => (clone $base)->where('status', 'ditanggapi')->count(),
        ];

        $q = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', '');
        $kategori = (string) $request->query('kategori', '');
        $tanggal = trim((string) $request->query('tanggal', ''));

        $query = (clone $base)->latest();
        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('subjek', 'like', "%{$q}%")
                    ->orWhere('pesan', 'like', "%{$q}%")
                    ->orWhere('id', is_numeric($q) ? (int) $q : -1);
            });
        }
        if (in_array($status, ['baru', 'dibaca', 'ditanggapi'], true)) {
            $query->where('status', $status);
        }
        if ($kategori === 'absensi') {
            $query->where('subjek', 'like', '[Kendala Absensi%');
        } elseif ($kategori === 'akun') {
            $query->where('subjek', 'like', '[Akun]%');
        } elseif ($kategori === 'saran') {
            $query->where(fn ($w) => $w->where('subjek', 'not like', '[Kendala Absensi%')->where('subjek', 'not like', '[Akun]%'));
        }
        if ($tanggal !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) {
            $query->whereDate('created_at', $tanggal);
        }

        $items = $query->paginate(6)->withQueryString();

        // Detail terpilih — selalu dibatasi kepemilikannya.
        $selectedId = (int) $request->query('selected');
        $selected = $selectedId > 0
            ? (clone $base)->whereKey($selectedId)->first()
            : null;
        $selected ??= $items->getCollection()->first();

        $items->through(fn (Suggestion $s) => self::mapSuggestionRow($s));

        return \Inertia\Inertia::render('Student/ReportHistory', [
            'items' => $items,
            'stats' => $stats,
            'filters' => ['q' => $q, 'status' => $status, 'kategori' => $kategori, 'tanggal' => $tanggal],
            'selected' => $selected ? self::mapSuggestionRow($selected) : null,
        ]);
    }

    /** Pemetaan satu baris laporan untuk React — kategori dari prefix subjek. */
    private static function mapSuggestionRow(Suggestion $s): array
    {
        $subjek = (string) ($s->subjek ?? '');
        $kategori = 'saran';
        $judul = $subjek !== '' ? $subjek : 'Laporan Mahasiswa';
        $tanggalAbsensi = null;

        if (preg_match('/^\[Kendala Absensi (\d{4}-\d{2}-\d{2})\]\s*(.*)$/u', $subjek, $m)) {
            $kategori = 'absensi';
            $tanggalAbsensi = $m[1];
            $judul = trim($m[2]) !== '' ? trim($m[2]) : 'Kendala Absensi';
        } elseif (preg_match('/^\[Akun\]\s*(.*)$/u', $subjek, $m)) {
            $kategori = 'akun';
            $judul = trim($m[1]) !== '' ? trim($m[1]) : 'Bantuan Akun';
        }

        $tz = 'Asia/Jakarta';
        return [
            'id' => $s->id,
            'ref' => '#' . str_pad((string) $s->id, 4, '0', STR_PAD_LEFT),
            'judul' => $judul,
            'kategori' => $kategori,
            'kategori_label' => match ($kategori) {
                'absensi' => 'Kendala Absensi',
                'akun' => 'Bantuan Akun',
                default => 'Saran & Masukan',
            },
            'tanggal_absensi' => $tanggalAbsensi,
            'status' => $s->status,
            'status_label' => match ($s->status) {
                'baru' => 'Menunggu',
                'dibaca' => 'Diproses',
                default => 'Selesai',
            },
            'tanggal_dikirim' => $s->created_at
                ? $s->created_at->timezone($tz)->format('d M Y, H:i') . ' WIB'
                : null,
            'tanggal_dikirim_tgl' => $s->created_at?->timezone($tz)->toDateString(),
            'cuplikan' => mb_strimwidth((string) $s->pesan, 0, 160, '…'),
            'pesan' => (string) $s->pesan,
            'lampiran_url' => $s->lampiran ? route('mahasiswa.suggestions.lampiran', $s->id) : null,
            'admin_reply' => $s->admin_reply,
            'admin_replied_at' => $s->admin_replied_at
                ? $s->admin_replied_at->timezone($tz)->format('d M Y, H:i') . ' WIB'
                : null,
            'balasan_belum_dibaca' => $s->admin_reply !== null && $s->user_read_reply_at === null,
        ];
    }

    public function storeSuggestion(Request $request): RedirectResponse|JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'kategori' => ['nullable', 'in:absensi,akun,saran,lainnya'],
            'subjek' => ['nullable', 'string', 'max:180'],
            'lampiran_token' => ['nullable', 'string', 'max:60', 'regex:/^[A-Za-z0-9]+\.(jpg|jpeg|png|webp)$/i'],
            'tanggal_absensi' => ['nullable', 'date'],
            'referensi_absensi' => ['nullable', 'string', 'max:180'],
            'pesan' => ['required', 'string', 'min:10', 'max:4000'],
        ], [
            'pesan.required' => 'Uraian laporan atau saran wajib diisi.',
            'pesan.min' => 'Uraian terlalu singkat (minimal 10 karakter).',
        ]);

        $kategori = $validated['kategori'] ?? (! empty($validated['tanggal_absensi']) ? 'absensi' : 'saran');
        $rawSubjek = trim((string) ($validated['subjek'] ?? ''));

        if ($kategori === 'absensi' && ! empty($validated['tanggal_absensi'])) {
            $tglFormatted = Carbon::parse($validated['tanggal_absensi'])->format('Y-m-d');
            $prefix = "[Kendala Absensi {$tglFormatted}]";
            $subjek = $rawSubjek !== ''
                ? (str_starts_with($rawSubjek, '[Kendala Absensi') ? $rawSubjek : "{$prefix} {$rawSubjek}")
                : "{$prefix} Laporan Kehadiran Mahasiswa";
        } elseif ($kategori === 'akun') {
            $subjek = $rawSubjek !== '' ? "[Akun] {$rawSubjek}" : '[Akun] Bantuan Akun Mahasiswa';
        } else {
            $subjek = $rawSubjek !== '' ? $rawSubjek : 'Saran & Masukan Mahasiswa';
        }
        $subjek = mb_substr($subjek, 0, 180);

        $pesan = trim((string) $validated['pesan']);
        if (! empty($validated['referensi_absensi'])) {
            $ref = trim((string) $validated['referensi_absensi']);
            if (! str_contains($pesan, $ref)) {
                $pesan = "Referensi Absensi: {$ref}\n\n" . $pesan;
            }
        }

        if ($limitMsg = \App\Support\SuggestionSubmissionLimiter::checkAccount($user->id, $pesan)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $limitMsg,
                ], 422);
            }

            return back()->withErrors(['pesan' => $limitMsg])->withInput();
        }

        \App\Support\SuggestionSubmissionLimiter::recordAccountHit($user->id);

        $suggestion = Suggestion::query()->create([
            'user_id' => $user->id,
            'source' => 'mahasiswa',
            'nama' => $user->nama_lengkap,
            'email' => $user->email,
            'subjek' => $subjek,
            'pesan' => $pesan,
            'status' => 'baru',
            'ip' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
        ]);

        // Foto bukti staged → pindah ke lampiran-saran/{id} (hanya jika token valid).
        $lampiranToken = (string) ($validated['lampiran_token'] ?? '');
        if ($lampiranToken !== '') {
            $disk = \Illuminate\Support\Facades\Storage::disk('local');
            $staged = "lampiran-tmp/{$user->id}/{$lampiranToken}";
            if ($disk->exists($staged)) {
                $target = "lampiran-saran/{$suggestion->id}." . pathinfo($staged, PATHINFO_EXTENSION);
                $disk->move($staged, $target);
                $suggestion->forceFill(['lampiran' => $target])->save();
            }
        }

        // Bangunkan kanal staf — badge Saran & Masukan menyegarkan tanpa reload.
        try {
            broadcast(new \App\Events\NotifikasiBaru('saran_baru', broadcastStaff: true));
        } catch (\Throwable $e) {
            // Broadcast opsional.
        }

        try {
            $staffIds = \App\Models\User::query()
                ->whereIn('role', ['laboran', 'admin'])
                ->where('is_active', true)
                ->pluck('id');
            app(\App\Services\WebPushService::class)->sendToUsers($staffIds, [
                'title' => 'Saran & masukan baru',
                'body' => 'Laporan ' . '#' . $suggestion->id . ' dari ' . ($request->user()->nama_lengkap ?? 'mahasiswa') . ' menunggu tinjauan.',
                'url' => route('admin.suggestions.index', ['status' => 'baru']),
                'tag' => 'saran-baru-' . $suggestion->id,
            ]);
        } catch (\Throwable $e) {
            // Web push opsional.
        }

        $ticketRef = '#' . $suggestion->id;
        $msg = "Laporan/saran {$ticketRef} berhasil dikirim ke Admin Laboran.";

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $msg,
                'ticket_id' => $suggestion->id,
                'ticket_ref' => $ticketRef,
                'status' => 'baru',
                'subjek' => $suggestion->subjek,
                'created_at' => $suggestion->created_at?->timezone('Asia/Jakarta')->format('d M Y, H:i') . ' WIB',
                'history_url' => route('mahasiswa.suggestions'),
            ]);
        }

        return to_route('mahasiswa.suggestions')->with('success', $msg);
    }

    public function readSuggestionReply(Request $request, Suggestion $suggestion): RedirectResponse|JsonResponse
    {
        abort_unless((int) $suggestion->user_id === (int) $request->user()->id, 403);
        $res = $this->callMobile($request, MobileDataController::class, 'markSuggestionReplyRead', [], $suggestion);

        if ($request->expectsJson()) {
            $state = $this->buildStudentNotifications($request->user(), 'all', 1, 12);

            return response()->json([
                'success' => (bool) ($res['success'] ?? true),
                'unread_count' => $state['unread_count'],
            ]);
        }

        return back()->with('success', 'Balasan ditandai sudah dibaca.');
    }

    /**
     * Asisten AI Bantuan Mahasiswa (read-only, terisolasi pada konteks mahasiswa yang login).
     * Tidak pernah mengirim laporan otomatis tanpa konfirmasi mahasiswa.
     */
    /**
     * Upload foto bukti (tahap staged) — token dikirim lagi saat laporan
     * disubmit; file hanya dipindah ke lampiran-saran setelah laporan dibuat.
     */
    public function uploadLampiran(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $validated = $request->validate([
            'foto' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
        ], [
            'foto.image' => 'File harus berupa gambar.',
            'foto.mimes' => 'Format yang didukung: JPG, PNG, atau WebP.',
            'foto.max' => 'Ukuran foto maksimal 3 MB.',
        ]);

        $file = $validated['foto'];
        $token = \Illuminate\Support\Str::random(32) . '.' . strtolower($file->getClientOriginalExtension());
        $path = "lampiran-tmp/{$user->id}/{$token}";
        \Illuminate\Support\Facades\Storage::disk('local')->putFileAs(
            "lampiran-tmp/{$user->id}", $file, $token
        );

        return response()->json([
            'ok' => true,
            'token' => $token,
            'nama' => mb_substr($file->getClientOriginalName(), 0, 80),
        ]);
    }

    /** Foto bukti laporan — hanya pemilik laporan. */
    public function suggestionLampiran(Request $request, Suggestion $suggestion)
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($suggestion->user_id === $user->id && $suggestion->lampiran, 404);
        $disk = \Illuminate\Support\Facades\Storage::disk('local');
        abort_unless($disk->exists($suggestion->lampiran), 404);
        return $disk->response($suggestion->lampiran);
    }

    /** Umpan balik 3 tingkat per-jawaban asisten — bahan pelatihan berikutnya. */
    public function helpFeedback(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'level' => ['required', 'string', 'in:bad,ok,great'],
            'question' => ['required', 'string', 'max:600'],
            'answer' => ['required', 'string', 'max:4000'],
        ]);
        $user = $request->user();
        $mark = strtoupper($validated['level']);
        // Topik ikut dicatat agar mudah dianalisis: topik mana yang jawabannya dinilai buruk.
        $topic = \App\Support\StudentLocalAssistant::topicOf((string) $validated['question']) ?? 'tak-dikenal';
        $line = '[' . now('Asia/Jakarta')->format('Y-m-d H:i') . "] {$mark} u{$user->id} topik={$topic}: "
            . 'Q: ' . mb_substr(preg_replace('/\s+/u', ' ', trim($validated['question'])), 0, 300)
            . ' | A: ' . mb_substr(preg_replace('/\s+/u', ' ', trim($validated['answer'])), 0, 300) . "\n";
        @file_put_contents(storage_path('logs/assistant-feedback.log'), $line, FILE_APPEND | LOCK_EX);
        return response()->json(['ok' => true]);
    }

    public function helpAi(Request $request, HelpdeskAiService $ai): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'message' => ['nullable', 'string', 'max:600'],
            'image_token' => ['nullable', 'string', 'max:60', 'regex:/^[A-Za-z0-9]+\.(jpg|jpeg|png|webp)$/i'],
            'history' => ['nullable', 'array', 'max:10'],
            'history.*.role' => ['required_with:history', 'string', 'in:user,assistant'],
            'history.*.text' => ['required_with:history', 'string', 'max:800'],
        ]);

        $message = trim((string) ($validated['message'] ?? ''));

        // Gambar staged dari upload lampiran — dipakai untuk analisis screenshot.
        $image = null;
        $imageToken = (string) ($validated['image_token'] ?? '');
        if ($imageToken !== '' && ! config('services.ai.attachments', true)) {
            return response()->json([
                'ok' => true,
                'reply' => 'Fitur kirim gambar ke asisten sedang dinonaktifkan sementara. Jelaskan kendala Anda lewat teks, atau kirim foto sebagai lampiran di **laporan resmi**.',
                'mode' => 'local_assistant',
            ]);
        }
        if ($imageToken !== '') {
            $staged = "lampiran-tmp/{$user->id}/{$imageToken}";
            $disk = \Illuminate\Support\Facades\Storage::disk('local');
            if ($disk->exists($staged) && $disk->size($staged) <= 4 * 1024 * 1024) {
                $mime = match (strtolower(pathinfo($staged, PATHINFO_EXTENSION))) {
                    'png' => 'image/png',
                    'webp' => 'image/webp',
                    default => 'image/jpeg',
                };
                $image = ['mime' => $mime, 'data' => base64_encode((string) $disk->get($staged))];
            }
        }

        if ($message === '' && $image === null) {
            return response()->json([
                'ok' => false,
                'reply' => 'Tulis pertanyaan terlebih dahulu.',
            ], 422);
        }
        if ($message === '') {
            $message = 'Tolong analisis gambar/tangkapan layar ini dan bantu selesaikan masalah saya.';
        }

        $shift = $user->shift ?: 'pagi';
        $todaySchedule = AbsensiEffectiveSchedule::resolve(now('Asia/Jakarta')->toDateString(), $shift);
        $geminiKey = trim((string) (config('services.gemini.api_key') ?? config('services.gemini.key') ?? ''));

        $issueKategori = self::detectIssueKategori($message);

        // Local deterministic intelligent responder when in test environment or when external AI key is not configured
        if (app()->environment('testing') || $geminiKey === '') {
            $reply = $image !== null
                ? "Gambar sudah saya terima, tapi analisis visual AI sedang tidak tersedia sementara. Jelaskan singkat kendala yang terlihat di gambar, atau kirim sebagai **foto bukti** di laporan resmi — saya lampirkan otomatis kalau Anda buat laporan dari sini."
                : $this->buildLocalStudentAiReply($user, $message, $todaySchedule, $validated['history'] ?? []);

            $payload = [
                'ok' => true,
                'reply' => $reply,
                'mode' => 'local_assistant',
            ];

            $reportData = self::issueReportData($issueKategori, $message, $todaySchedule, $user)
                ?? ($image !== null ? self::imageReportData() : null);
            if ($reportData) {
                $payload['suggest_report'] = true;
                $payload['report_data'] = $reportData;
            }

            return response()->json($payload);
        }

        $history = collect($validated['history'] ?? [])
            ->map(fn (array $m) => [
                'role' => $m['role'],
                'text' => trim((string) $m['text']),
                'content' => trim((string) $m['text']),
            ])
            ->filter(fn (array $m) => $m['text'] !== '')
            ->values()
            ->all();

        // Fakta akun nyata agar AI menjawab kontekstual (bukan tebakan umum).
        $timAi = \App\Support\RuanganBookingService::timMahasiswa($user);
        $timInfo = $timAi
            ? sprintf('%s%s — Manpro: %s — %d anggota',
                $timAi->nama,
                $timAi->kode ? " ({$timAi->kode})" : '',
                $timAi->manproUser?->nama_lengkap ?? $timAi->manpro ?? '—',
                \App\Support\RuanganBookingService::anggotaTim($timAi)->count())
            : 'BELUM ditugaskan ke tim PBL mana pun (penugasan oleh dosen pengampu/laboran)';

        $studentContextPrompt = sprintf(
            "Anda adalah asisten virtual cerdas, ramah, dan solutif untuk Portal Mahasiswa SIMALAS (Sistem Absensi & Manajemen Lab Politeknik Negeri Batam).\n"
            . "Gunakan bahasa Indonesia yang santun, komunikatif, bersahabat, dan manusiawi (tidak kaku/robotik).\n"
            . "Data Mahasiswa yang sedang bertanya:\n"
            . "- Nama: %s\n"
            . "- NIM: %s\n"
            . "- Sesi Kuliah: %s\n"
            . "- Jadwal Hari Ini (%s): %s (%s)\n"
            . "- Kebijakan Jadwal Aktif: %s\n"
            . "- Tim PBL: %s\n"
            . "- Kartu akses ruangan: %s\n"
            . "- Password sementara belum diganti: %s\n"
            . "- Sidik jari absensi: %s\n\n"
            . "Ketentuan:\n"
            . "1. Jawab pertanyaan mahasiswa dengan jelas, ramah, dan to the point — selalu dari data di atas, jangan mengarang.\n"
            . "2. Anda bersifat konsultatif & informatif. Anda TIDAK BISA mengubah data database atau status absensi secara otomatis.\n"
            . "3. Jangan menyebut kata 'tab Kirim Laporan'. Tombol laporan resmi sudah terpasang di widget chat ('Kirim Laporan Resmi') atau konfirmasi kendala.\n"
            . "4. Jika mahasiswa mengalami kendala apa pun (tap, alfa, akun, tim PBL, akses ruangan), jelaskan penyebab/lalu tawarkan Laporan Resmi ke Admin Laboran.\n"
            . "5. Jika mahasiswa belum punya tim PBL, jelaskan bahwa penugasan dilakukan dosen pengampu/laboran dan sarankan konfirmasi ke mereka atau kirim laporan resmi.\n"
            . "6. Pengajuan ruangan dilakukan tim PBL lewat menu Ruangan, paling lambat H-1 sebelum pemakaian; akses masuk ruangan hanya berlaku selama ada izin aktif.\n"
            . "7. Jika password masih sementara, jelaskan bahwa fitur tetap bisa dipakai namun sebaiknya diganti lewat avatar → Keamanan.\n\n"
            . "8. Jika mahasiswa melampirkan gambar/screenshot, analisis isinya (pesan error, tampilan portal, kartu akses) dan bantu selesaikan langkah demi langkah.\n"
            . "9. JUJUR soal keterbatasan: jika gambar buram, tidak terbaca, tidak relevan dengan portal, atau Anda tidak yakin — katakan terus terang dan tawarkan laporan resmi ke Admin Laboran. JANGAN mengarang diagnosis atau mengklaim melihat sesuatu yang tidak ada.\n"
            . "10. BATAS TOPIK & DATA: hanya bahas layanan portal SmartBrail/SIMALAS dan data mahasiswa di atas. Jika diminta hal di luar itu (data orang lain, kredensial, topik umum), tolak sopan dan arahkan kembali. Jangan pernah menampilkan atau meminta password/PIN/kode OTP.\n\n"
            . "Pertanyaan Mahasiswa: %s",
            $user->nama_lengkap,
            $user->nitk_nidn,
            $shift,
            $todaySchedule['hari_nama'] ?? 'Hari ini',
            $todaySchedule['label_jadwal'] ?? '07:50 - 17:00 WIB',
            $todaySchedule['label_status_hari'] ?? 'Jadwal Kuliah Lab',
            AbsensiKebijakan::labelMode(),
            $timInfo,
            $user->rfid_uid ?: 'belum terbit',
            $user->must_change_password ? 'Ya' : 'Tidak',
            $user->fingerprint_id ? "terdaftar (#{$user->fingerprint_id})" : 'belum terdaftar',
            $message
        );

        $result = $ai->reply($studentContextPrompt, $history, $image);

        $issueKategori = self::detectIssueKategori($message);

        $geminiOk = ! empty($result['reply']) && in_array($result['source'] ?? '', ['gemini', 'groq', 'vision_fallback'], true);
        $replyText = $geminiOk
            ? $result['reply']
            : ($image !== null
                ? "Gambar sudah saya terima, tapi analisis visual AI sedang sibuk/terbatas sementara — silakan coba lagi beberapa menit, atau jelaskan kendalanya lewat teks. Anda juga bisa mengirim gambar ini sebagai **foto bukti** lewat laporan resmi."
                : $this->buildLocalStudentAiReply($user, $message, $todaySchedule, $validated['history'] ?? []));

        $responsePayload = [
            'ok' => true,
            'reply' => $replyText,
            'mode' => $geminiOk ? 'ai_assistant' : 'local_assistant',
        ];

        $reportData = self::issueReportData($issueKategori, $message, $todaySchedule, $user)
            ?? ($image !== null ? self::imageReportData() : null);
        if ($reportData) {
            $responsePayload['suggest_report'] = true;
            $responsePayload['report_data'] = $reportData;
        }

        return response()->json($responsePayload);
    }

    /**
     * Konteks data nyata untuk widget Asisten SmartBrail — seluruhnya dibatasi
     * pada data milik mahasiswa yang sedang login (tim, pengajuan, laporan).
     */
    public function helpContext(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $tz = 'Asia/Jakarta';
        $today = now($tz);
        $shift = $user->shift ?: 'pagi';
        $jadwal = AbsensiEffectiveSchedule::resolve($today->toDateString(), $shift);

        $tim = RuanganBookingService::timMahasiswa($user);
        $anggota = $tim ? RuanganBookingService::anggotaTim($tim) : collect();

        $statusLabel = [
            'diajukan' => 'Menunggu keputusan',
            'menunggu_penerimaan' => 'Tawaran waktu pengganti',
            'disetujui' => 'Disetujui',
            'ditolak' => 'Ditolak',
            'dibatalkan' => 'Dibatalkan',
            'selesai' => 'Selesai',
            'kedaluwarsa' => 'Kedaluwarsa',
        ];

        $pengajuan = RuanganPengajuan::with('ruangan:id,nama')
            ->when($tim, fn ($q) => $q->where('pbl_project_id', $tim->id))
            ->when(! $tim, fn ($q) => $q->where('pengaju_id', $user->id))
            ->latest('tanggal')->latest('jam_mulai')
            ->limit(5)->get()
            ->map(fn (RuanganPengajuan $p) => [
                'id' => $p->id,
                'ruangan' => $p->ruangan?->nama ?? '—',
                'tanggal' => $p->tanggal?->toDateString(),
                'tanggal_human' => $p->tanggal ? Carbon::parse($p->tanggal)->timezone($tz)->translatedFormat('d M Y') : null,
                'jam' => substr((string) $p->jam_mulai, 0, 5) . '–' . substr((string) $p->jam_selesai, 0, 5),
                'status' => $p->statusEfektif(),
                'status_label' => $statusLabel[$p->statusEfektif()] ?? $p->statusEfektif(),
            ])->values();

        $laporan = Suggestion::query()
            ->where('user_id', $user->id)
            ->latest('id')->limit(5)->get()
            ->map(fn (Suggestion $s) => [
                'id' => $s->id,
                'ref' => '#' . str_pad((string) $s->id, 4, '0', STR_PAD_LEFT),
                'judul' => self::mapSuggestionRow($s)['judul'],
                'status' => $s->status,
                'status_label' => self::mapSuggestionRow($s)['status_label'],
            ])->values();

        return response()->json([
            'ok' => true,
            'user' => [
                'nama_pendek' => explode(' ', trim($user->nama_lengkap))[0] ?? 'Mahasiswa',
            ],
            'jadwal' => [
                'hari_nama' => $jadwal['hari_nama'] ?? null,
                'label_jadwal' => $jadwal['label_jadwal'] ?? null,
                'label_status_hari' => $jadwal['label_status_hari'] ?? null,
                'is_wajib' => (bool) ($jadwal['is_wajib'] ?? false),
            ],
            'tim' => $tim ? [
                'nama' => $tim->nama,
                'kode' => $tim->kode ?? null,
                'manpro' => $tim->manpro ?? null,
                'anggota_count' => $anggota->count(),
            ] : null,
            'anggota' => $anggota->map(fn (User $u) => [
                'id' => $u->id,
                'nama_lengkap' => $u->nama_lengkap,
                'nitk_nidn' => $u->nitk_nidn,
            ])->values()->all(),
            'ruangan' => Ruangan::where('aktif', true)->orderBy('nama')->get()
                ->map(fn (Ruangan $r) => [
                    'id' => $r->id,
                    'nama' => $r->nama,
                    'kode' => $r->kode,
                    'kapasitas' => $r->kapasitas,
                    'jam_buka' => $r->aturan()['jam_buka'] ?? '07:00',
                    'jam_tutup' => $r->aturan()['jam_tutup'] ?? '21:00',
                    'max_duration_minutes' => $r->aturan()['max_duration_minutes'] ?? 120,
                    'min_advance_days' => $r->aturan()['min_advance_days'] ?? 0,
                    'max_advance_days' => $r->aturan()['max_advance_days'] ?? 2,
                    'durasi_opsi' => $r->aturan()['durasi_opsi'] ?? [30, 60, 90, 120],
                ])->values()->all(),
            'pengajuan' => $pengajuan->all(),
            'laporan' => $laporan->all(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function enrichAttendanceRow(array $row, string $defaultShift = 'pagi'): array
    {
        $tanggal = (string) ($row['tanggal'] ?? now('Asia/Jakarta')->toDateString());
        $sesi = (string) ($row['sesi'] ?? $defaultShift);
        $jadwal = AbsensiEffectiveSchedule::resolve($tanggal, $sesi);
        $penilaian = AbsensiEffectiveSchedule::assessRecord($jadwal, $row);

        // Riwayat tidak menyimpan salinan jadwal saat tap. Bila menit terlambat yang tersimpan
        // tidak cocok dengan jadwal yang berlaku sekarang, penilaian berasal dari pengaturan lama.
        // Kita tidak menilai ulang; tampilan hanya diberi tanda asal penilaian.
        $jadwalKonsisten = AbsensiEffectiveSchedule::scheduleConsistentWithRecord($jadwal, $row);

        return array_merge($row, [
            'tanggal_human' => Carbon::parse($tanggal)->translatedFormat('d M Y'),
            'tanggal_hari' => Carbon::parse($tanggal)->translatedFormat('D, d M'),
            'jadwal_konsisten' => $jadwalKonsisten,
            'hari_nama' => $jadwal['hari_nama'],
            'jadwal_masuk' => $jadwal['jam_masuk'],
            'jadwal_pulang' => $jadwal['jam_pulang'],
            'jadwal_label' => $jadwal['label_jadwal'],
            'status_hari' => $jadwal['status_hari'],
            'sumber_aturan' => $jadwal['sumber_aturan'],
            'agenda_hari' => $jadwal['agenda'],
            'penilaian' => $penilaian,
            'kelengkapan_kode' => $penilaian['kelengkapan_kode'],
            'kelengkapan_label' => $penilaian['kelengkapan_label'],
            'kelengkapan_tone' => $penilaian['kelengkapan_tone'],
            'kelengkapan_desc' => $penilaian['kelengkapan_desc'],
            'ketepatan_masuk_kode' => $penilaian['ketepatan_masuk_kode'],
            'ketepatan_masuk_label' => $penilaian['ketepatan_masuk_label'],
            'ketepatan_masuk_tone' => $penilaian['ketepatan_masuk_tone'],
            'ketepatan_pulang_kode' => $penilaian['ketepatan_pulang_kode'],
            'ketepatan_pulang_label' => $penilaian['ketepatan_pulang_label'],
            'ketepatan_pulang_tone' => $penilaian['ketepatan_pulang_tone'],
            'ringkasan_ketepatan' => $penilaian['ringkasan_ketepatan'],
            'ringkasan_ketepatan_tone' => $penilaian['ringkasan_ketepatan_tone'],
            'menit_pulang_cepat' => $penilaian['menit_pulang_cepat'],
            'has_punctuality_note' => $penilaian['has_punctuality_note'],
        ]);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $enrichedHarian
     * @return array<string, mixed>
     */
    /**
     * Data ruangan untuk beranda mahasiswa: pengajuan tim, izin berlaku, perhatian,
     * dan agenda kalender. Maksimal 2 query — tidak ada query per tanggal/kartu.
     */
    private function ruanganDashboardData(User $user, $tim, Carbon $today, string $monthParam): array
    {
        $tz = 'Asia/Jakarta';
        $now = $today->copy();
        $monthStart = Carbon::createFromFormat('Y-m', $monthParam, $tz)->startOfMonth();
        $rangeEnd = $today->copy()->addDays(60)->toDateString();

        $rows = RuanganPengajuan::with('ruangan:id,nama,kode,foto')
            ->where(fn ($q) => $tim
                ? $q->where('pbl_project_id', $tim->id)
                : $q->where('pengaju_id', $user->id))
            ->whereBetween('tanggal', [$monthStart->toDateString(), $rangeEnd])
            ->where('status', '!=', RuanganPengajuan::STATUS_DIBATALKAN)
            ->orderBy('tanggal')->orderBy('jam_mulai')
            ->limit(100)->get();

        $izinSekarang = RuanganIzin::berlakuPada($now)
            ->where('user_id', $user->id)
            ->with('ruangan:id,nama,kode')
            ->get();
        $izinPengajuanIds = $izinSekarang->pluck('pengajuan_id')->map(fn ($i) => (int) $i)->all();

        $berikutnya = $rows->first(fn (RuanganPengajuan $p) =>
            $p->statusEfektif() === RuanganPengajuan::STATUS_DISETUJUI && $p->selesaiAt()->gte($now));
        if ($berikutnya?->ruangan) {
            $berikutnya->ruangan->setAttribute(
                'foto_url',
                $berikutnya->ruangan->foto ? asset($berikutnya->ruangan->foto) : null
            );
        }

        $perhatian = $rows->filter(function (RuanganPengajuan $p) use ($user, $now) {
                if ($p->status === RuanganPengajuan::STATUS_MENUNGGU_PENERIMAAN && (int) $p->pengaju_id === (int) $user->id) {
                    return true; // tawaran waktu pengganti menunggu keputusan pengaju
                }
                // Keputusan baru (≤7 hari) — informatif, bukan menumpuk "diajukan" permanen.
                return in_array($p->status, [RuanganPengajuan::STATUS_DISETUJUI, RuanganPengajuan::STATUS_DITOLAK], true)
                    && $p->diputus_pada && $p->diputus_pada->gte($now->copy()->subDays(7));
            })->sortBy(fn (RuanganPengajuan $p) => $p->status === RuanganPengajuan::STATUS_MENUNGGU_PENERIMAAN ? 0 : 1)
            ->take(4)->values();

        $statusLabel = [
            'diajukan' => 'Menunggu keputusan',
            'menunggu_penerimaan' => 'Tawaran waktu pengganti',
            'disetujui' => 'Disetujui',
            'selesai' => 'Selesai',
        ];

        return [
            'tim' => $tim,
            // Sumber sama dengan daftar peserta pengajuan: gabungan pbl_team_members + legacy users.tim.
            'anggota_count' => $tim ? RuanganBookingService::anggotaTim($tim)->count() : 0,
            'ketua' => $tim ? $tim->ketuaMember()->with('user:id,nama_lengkap')->first()?->user?->nama_lengkap : null,
            'berikutnya' => $berikutnya,
            'izin_berlaku' => $berikutnya && in_array((int) $berikutnya->id, $izinPengajuanIds, true),
            'perhatian' => $perhatian,
            'kalender_ruangan' => $rows
                ->filter(fn (RuanganPengajuan $p) => in_array($p->statusEfektif(), [
                    RuanganPengajuan::STATUS_DIAJUKAN,
                    RuanganPengajuan::STATUS_MENUNGGU_PENERIMAAN,
                    RuanganPengajuan::STATUS_DISETUJUI,
                ], true))
                ->groupBy(fn (RuanganPengajuan $p) => $p->tanggal->toDateString())
                ->map(fn ($group) => $group->map(fn (RuanganPengajuan $p) => [
                    'ruangan' => $p->ruangan?->nama ?? '—',
                    'jam' => substr((string) $p->jam_mulai, 0, 5) . '–' . substr((string) $p->jam_selesai, 0, 5),
                    'status' => $p->statusEfektif(),
                    'status_label' => $statusLabel[$p->statusEfektif()] ?? $p->statusEfektif(),
                ])->values()->all())
                ->all(),
        ];
    }

    /** Agenda ruangan untuk kalender JSON — dipakai navigasi bulan tanpa reload. */
    private function ruanganCalendarEvents(User $user, string $monthParam): array
    {
        $tim = RuanganBookingService::timMahasiswa($user);
        $bms = $this->ruanganDashboardData($user, $tim, now('Asia/Jakarta'), $monthParam);

        return $bms['kalender_ruangan'];
    }

    private function buildMonthCalendar(string $yearMonth, string $shift, $enrichedHarian, string $todayStr, array $ruanganEvents = []): array
    {
        $startOfMonth = Carbon::createFromFormat('Y-m-d', $yearMonth . '-01', 'Asia/Jakarta')->startOfMonth();
        $endOfMonth = $startOfMonth->copy()->endOfMonth();
        $byDate = $enrichedHarian->keyBy('tanggal');

        $days = [];
        $agendasInMonth = [];
        $cursor = $startOfMonth->copy();

        while ($cursor->lessThanOrEqualTo($endOfMonth)) {
            $dStr = $cursor->toDateString();
            $resolved = AbsensiEffectiveSchedule::resolve($dStr, $shift);
            $att = $byDate->get($dStr);

            $agendaItems = array_map(fn (array $ag) => array_merge($ag, [
                'jenis_label' => self::AGENDA_JENIS_LABELS[$ag['jenis']] ?? ucfirst((string) $ag['jenis']),
                'dampak_label' => self::AGENDA_DAMPAK_LABELS[$ag['dampak_absensi']] ?? (string) $ag['dampak_absensi'],
            ]), $resolved['agenda']);

            foreach ($agendaItems as $ag) {
                $agendasInMonth[$ag['id']] = $ag;
            }

            $days[] = [
                'tanggal' => $dStr,
                'tanggal_human' => $cursor->translatedFormat('l, d F Y'),
                'day_num' => (int) $cursor->day,
                'dow' => (int) $cursor->dayOfWeek,
                'hari_nama' => $resolved['hari_nama'],
                'is_today' => $dStr === $todayStr,
                'is_wajib' => $resolved['is_wajib'],
                'is_libur' => $resolved['is_libur'],
                'status_hari' => $resolved['status_hari'],
                'sumber_label' => self::SUMBER_ATURAN_LABELS[$resolved['sumber_aturan']] ?? $resolved['sumber_aturan'],
                'jam_masuk' => $resolved['jam_masuk'],
                'jam_pulang' => $resolved['jam_pulang'],
                'label_jadwal' => $resolved['label_jadwal'],
                'label_status_hari' => $resolved['label_status_hari'],
                'agenda' => $agendaItems,
                'ruangan' => $ruanganEvents[$dStr] ?? [],
                'absensi' => $att ? [
                    'kelengkapan_kode' => $att['kelengkapan_kode'],
                    'kelengkapan_label' => $att['kelengkapan_label'],
                    'kelengkapan_tone' => $att['kelengkapan_tone'],
                    'jam_masuk' => $att['jam_masuk'] ?? null,
                    'jam_pulang' => $att['jam_pulang'] ?? null,
                    'ketepatan_masuk_label' => $att['ketepatan_masuk_label'],
                    'ketepatan_pulang_label' => $att['ketepatan_pulang_label'],
                    'jadwal_konsisten' => $att['jadwal_konsisten'],
                ] : null,
                // kompatibilitas tampilan lama
                'absensi_status' => $att['kelengkapan_label'] ?? null,
                'absensi_tone' => $att['kelengkapan_tone'] ?? null,
                'absensi_masuk' => $att['jam_masuk'] ?? null,
                'absensi_pulang' => $att['jam_pulang'] ?? null,
                'absensi_ketepatan' => $att['ringkasan_ketepatan'] ?? null,
            ];

            $cursor->addDay();
        }

        // First day offset for Monday-first calendar grid (Monday=0 .. Sunday=6)
        $firstDowMondayIndex = ((int) $startOfMonth->dayOfWeek + 6) % 7;

        return [
            'month_value' => $yearMonth,
            'month_label' => $startOfMonth->translatedFormat('F Y'),
            // Alias kontrak frontend React (CalendarData): bulan/bulan_label dipakai Dashboard & kalender.
            'bulan' => $yearMonth,
            'bulan_label' => $startOfMonth->translatedFormat('F Y'),
            'prev' => $startOfMonth->copy()->subMonth()->format('Y-m'),
            'next' => $startOfMonth->copy()->addMonth()->format('Y-m'),
            'weeks_start' => $firstDowMondayIndex,
            'prev_month' => $startOfMonth->copy()->subMonth()->format('Y-m'),
            'next_month' => $startOfMonth->copy()->addMonth()->format('Y-m'),
            'today_month' => substr($todayStr, 0, 7),
            'today' => $todayStr,
            'first_offset' => $firstDowMondayIndex,
            'days' => $days,
            'agendas' => array_values($agendasInMonth),
        ];
    }

    private const AGENDA_JENIS_LABELS = [
        'libur' => 'Libur',
        'perubahan_jam' => 'Perubahan jam',
        'ujian' => 'Ujian',
        'informasi' => 'Informasi',
        'peringatan' => 'Peringatan Nasional',
        'agenda' => 'Agenda Kegiatan',
    ];

    private const AGENDA_DAMPAK_LABELS = [
        'batal_wajib' => 'Absensi tidak wajib pada tanggal ini',
        'ubah_jam' => 'Absensi tetap wajib dengan jam khusus',
        'tetap_wajib' => 'Tidak mengubah kewajiban absensi',
    ];

    private const SUMBER_ATURAN_LABELS = [
        'jadwal_mingguan' => 'Jadwal mingguan',
        'kebijakan_hari_wajib' => 'Kebijakan hari wajib',
        'kebijakan_sesi' => 'Pengaturan sesi',
        'sebelum_tanggal_efektif' => 'Kebijakan sebelum tanggal efektif',
        'menunggu_tanggal_efektif' => 'Kebijakan lama (jadwal mingguan menunggu tanggal efektif)',
        'pengecualian_libur' => 'Kalender akademik: libur',
        'pengecualian_jam' => 'Kalender akademik: perubahan jam',
    ];

    /**
     * Ringkasan kebijakan jadwal yang benar-benar aktif (dibaca dari pengaturan, tidak diasumsikan).
     *
     * @return array<string, mixed>
     */
    private function scheduleMeta(string $shift, string $todayStr): array
    {
        $cfg = AbsensiKebijakan::config();
        $mode = AbsensiKebijakan::mode();
        $sesi = \App\Models\AbsensiSesiSetting::forShift($shift);
        $sesiAktif = (bool) $sesi->aktif;
        $effectiveFrom = ! empty($cfg['effective_from']) ? (string) $cfg['effective_from'] : null;
        // Pending = tanggal efektif belum ditetapkan ATAU hari ini masih sebelum tanggal efektif.
        $pendingWeekly = $mode === AbsensiKebijakan::MODE_MINGGUAN
            && ($effectiveFrom === null || $todayStr < $effectiveFrom);
        $activeMode = $pendingWeekly ? (string) ($cfg['legacy_mode'] ?? AbsensiKebijakan::MODE_KAMIS) : $mode;

        $sesiMasuk = substr((string) $sesi->jam_masuk, 0, 5);
        $sesiPulang = substr((string) $sesi->jam_pulang, 0, 5);

        $buildWeekly = function (string $forMode, array $hariWajib) use ($cfg, $shift, $sesiAktif, $sesiMasuk, $sesiPulang): array {
            $rows = [];
            $days = AbsensiEffectiveSchedule::normalizeWeeklyDays($cfg['jadwal_mingguan'] ?? []);
            foreach ([1, 2, 3, 4, 5, 6, 0] as $dow) {
                if ($forMode === AbsensiKebijakan::MODE_MINGGUAN) {
                    $wajib = ! empty($days[$dow]['aktif']) && $sesiAktif;
                    $masuk = $shift === 'pagi' ? $days[$dow]['jam_masuk'] : $sesiMasuk;
                    $pulang = $shift === 'pagi' ? $days[$dow]['jam_pulang'] : $sesiPulang;
                } else {
                    $wajib = in_array($dow, $hariWajib, true) && $sesiAktif;
                    $masuk = $sesiMasuk;
                    $pulang = $sesiPulang;
                }
                $rows[] = [
                    'dow' => $dow,
                    'hari' => AbsensiEffectiveSchedule::DAY_NAMES[$dow],
                    'wajib' => $wajib,
                    'jam_masuk' => $masuk,
                    'jam_pulang' => $pulang,
                ];
            }

            return $rows;
        };

        $activeHariWajib = $pendingWeekly
            ? array_map('intval', (array) ($cfg['legacy_hari_wajib'] ?? [4]))
            : array_map('intval', (array) ($cfg['hari_wajib'] ?? [4]));
        $weekly = $buildWeekly($activeMode, $activeHariWajib);
        $upcoming = $pendingWeekly ? $buildWeekly(AbsensiKebijakan::MODE_MINGGUAN, []) : null;

        $wajibNames = collect($weekly)->where('wajib', true)->pluck('hari')->all();
        $activeLabel = $wajibNames === [] ? 'Tidak ada hari wajib' : implode(', ', $wajibNames);

        return [
            'mode' => $mode,
            'active_mode' => $activeMode,
            'active_label' => $activeLabel,
            'weekly' => $weekly,
            'upcoming_weekly' => $upcoming,
            'effective_from' => $effectiveFrom,
            'effective_from_label' => $effectiveFrom ? Carbon::parse($effectiveFrom)->translatedFormat('d F Y') : null,
            'pending_weekly' => $pendingWeekly,
            'sesi_aktif' => $sesiAktif,
            'shift' => $shift,
            'toleransi_masuk' => (int) $sesi->toleransi_masuk_menit,
            'kalender_lengkap' => Schema::hasTable('absensi_kalender_libur')
                && Schema::hasColumn('absensi_kalender_libur', 'dampak_absensi'),
        ];
    }

    /**
     * Kondisi absensi hari ini yang dibedakan secara eksplisit.
     *
     * @param  array<string, mixed>  $schedule
     * @param  array<string, mixed>|null  $record
     * @param  array<string, mixed>  $meta
     * @return array{kode: string, judul: string, keterangan: string, tone: string, sumber: string, pengecualian: ?string}
     */
    private function todayState(array $schedule, ?array $record, Carbon $now, array $meta): array
    {
        $tz = $schedule['timezone'] ?? 'Asia/Jakarta';
        $dateStr = $schedule['tanggal'];
        $masukAt = Carbon::parse("{$dateStr} {$schedule['jam_masuk']}", $tz);
        $pulangAt = Carbon::parse("{$dateStr} {$schedule['jam_pulang']}", $tz);
        $bukaAt = Carbon::parse("{$dateStr} {$schedule['jam_mulai_tap_masuk']}", $tz);
        $sumber = self::SUMBER_ATURAN_LABELS[$schedule['sumber_aturan']] ?? (string) $schedule['sumber_aturan'];
        $pengecualian = null;
        foreach ($schedule['agenda'] ?? [] as $ag) {
            $pengecualian = (self::AGENDA_JENIS_LABELS[$ag['jenis']] ?? 'Agenda') . ': ' . $ag['keterangan'];
            break;
        }

        $make = fn (string $kode, string $judul, string $ket, string $tone) => [
            'kode' => $kode,
            'judul' => $judul,
            'keterangan' => $ket,
            'tone' => $tone,
            'sumber' => $sumber,
            'pengecualian' => $pengecualian,
        ];

        if (! $meta['sesi_aktif'] && ! $record) {
            return $make('sesi_nonaktif', 'Sesi absensi nonaktif', 'Sesi ' . $meta['shift'] . ' sedang dinonaktifkan admin, sehingga tidak ada penilaian kehadiran hari ini.', 'neutral');
        }

        if ($schedule['is_libur'] && ! $record) {
            return $make('libur', 'Libur', $schedule['label_status_hari'] . '. Tidak ada kewajiban absensi.', 'neutral');
        }

        if ($record) {
            $status = (string) ($record['status'] ?? '');

            return match (true) {
                $status === 'lengkap' => $make('lengkap', 'Rekaman lengkap', 'Tap masuk dan tap pulang sudah tercatat.', 'ok'),
                in_array($status, ['masuk', 'belum_lengkap'], true) && $now->lessThan($pulangAt) => $make('berjalan', 'Sudah tap masuk', 'Sesi berlangsung sampai ' . $pulangAt->format('H:i') . '. Lakukan tap pulang sebelum meninggalkan lab.', 'info'),
                in_array($status, ['masuk', 'belum_lengkap'], true) => $make('belum_pulang', 'Belum tap pulang', 'Jam pulang ' . $pulangAt->format('H:i') . ' sudah lewat. Status akhir ditetapkan saat sistem menutup sesi.', 'warn'),
                $status === 'tidak_lengkap' => $make('tidak_lengkap', 'Tidak lengkap', 'Tap pulang tidak tercatat sampai sesi ditutup.', 'warn'),
                $status === 'alfa' => $make('alfa', 'Alfa', 'Tidak ada tap pada jadwal wajib hari ini.', 'danger'),
                default => $make($status ?: 'tercatat', ucfirst($status ?: 'Tercatat'), 'Status kehadiran tercatat oleh admin.', 'info'),
            };
        }

        if (! $schedule['is_wajib']) {
            return $make('tidak_wajib', 'Tidak ada jadwal wajib', 'Hari ' . $schedule['hari_nama'] . ' bukan hari wajib menurut kebijakan aktif (hari wajib: ' . $meta['active_label'] . ').', 'neutral');
        }

        if ($now->lessThan($bukaAt)) {
            return $make('belum_dibuka', 'Tap belum dibuka', 'Tap masuk dibuka pukul ' . $bukaAt->format('H:i') . ', jadwal masuk ' . $masukAt->format('H:i') . '.', 'neutral');
        }

        if ($now->lessThan($pulangAt)) {
            return $make('belum_tap', 'Belum ada tap masuk', $now->lessThanOrEqualTo($masukAt)
                ? 'Jadwal masuk pukul ' . $masukAt->format('H:i') . '.'
                : 'Jadwal masuk ' . $masukAt->format('H:i') . ' sudah lewat. Tap tetap dicatat dengan keterangan terlambat.', 'info');
        }

        return $make('belum_tap_lewat', 'Belum ada tap', 'Jadwal hari ini sudah berakhir tanpa tap tercatat. Status akhir ditetapkan saat sistem menutup sesi.', 'warn');
    }

    /**
     * Build shared topbar notification state for layouts/student.blade.php.
     *
     * @return array{navNotificationState: array<string, mixed>}
     */
    private function sharedLayoutData(User $user): array
    {
        return [
            'navNotificationState' => $this->sharedNotificationState($user),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function sharedNotificationState(User $user): array
    {
        return $this->buildStudentNotifications($user, 'unread', 1, 20);
    }

    /**
     * @return array{unread_count: int, has_more: bool, items: array<int, array<string, mixed>>}
     */
    private function buildStudentNotifications(User $user, string $filter = 'all', int $page = 1, int $perPage = 15): array
    {
        $announcements = Announcement::active()
            ->where('channel', '!=', Announcement::CHANNEL_NOTIFIKASI_LOKER)
            ->forAudience($user)
            ->with('author:id,nama_lengkap')
            ->latest('created_at')
            ->limit(40)
            ->get();

        $readAnnIds = DB::table('announcement_reads')
            ->where('user_id', $user->id)
            ->whereIn('announcement_id', $announcements->pluck('id'))
            ->pluck('read_at', 'announcement_id');

        $annItems = $announcements->map(function (Announcement $a) use ($readAnnIds) {
            $readAt = $readAnnIds[$a->id] ?? null;
            $ts = $a->created_at;

            return [
                'uid' => 'ann-' . $a->id,
                'id' => (int) $a->id,
                'kind' => 'announcement',
                'category_label' => 'Pengumuman',
                'priority' => $a->prioritas ?? 'normal',
                'title' => (string) $a->judul,
                'excerpt' => \Illuminate\Support\Str::limit(strip_tags((string) $a->isi), 110),
                'body' => (string) $a->isi,
                'meta' => ($a->author?->nama_lengkap ?: 'Admin Laboran'),
                'timestamp' => optional($ts)->toIso8601String(),
                'time_human' => optional($ts)->timezone('Asia/Jakarta')->translatedFormat('d M Y, H:i') . ' WIB',
                'is_read' => $readAt !== null,
                'read_url' => route('mahasiswa.notifications.read', $a->id),
            ];
        });

        $replies = Suggestion::query()
            ->with('repliedBy:id,nama_lengkap')
            ->where('user_id', $user->id)
            ->whereNotNull('admin_reply')
            ->where('admin_reply', '!=', '')
            ->orderByDesc('admin_replied_at')
            ->limit(30)
            ->get();

        $replyItems = $replies->map(function (Suggestion $s) {
            $ts = $s->admin_replied_at ?? $s->updated_at;

            $cleanSubjek = trim((string) preg_replace('/^\[(Kendala Absensi [^\]]+|Akun)\]\s*/u', '', (string) $s->subjek));
            if ($cleanSubjek === '') {
                $cleanSubjek = 'Laporan #' . $s->id;
            }

            return [
                'uid' => 'rep-' . $s->id,
                'id' => (int) $s->id,
                'kind' => 'suggestion_reply',
                'category_label' => 'Balasan Laporan',
                'priority' => 'penting',
                'title' => 'Balasan Admin Laboran',
                'excerpt' => \Illuminate\Support\Str::limit('Re: ' . $cleanSubjek . ' — "' . trim(strip_tags((string) $s->admin_reply)) . '"', 110),
                'body' => "Tanggapan Admin Laboran:\n" . $s->admin_reply . "\n\n———\nLaporan Anda (Referensi #{$s->id} — {$cleanSubjek}):\n" . $s->pesan,
                'meta' => ($s->repliedBy?->nama_lengkap ?? 'Admin Laboran'),
                'timestamp' => optional($ts)->toIso8601String(),
                'time_human' => optional($ts)->timezone('Asia/Jakarta')->translatedFormat('d M Y, H:i') . ' WIB',
                'is_read' => $s->user_read_reply_at !== null,
                'read_url' => route('mahasiswa.suggestions.read', $s->id),
                'action_url' => route('mahasiswa.suggestions'),
                'action_label' => 'Buka di Riwayat Bantuan',
            ];
        });

        // Keputusan pengajuan ruangan — dibangun dari baris transaksi (diputus_oleh,
        // diputus_pada) sehingga nama pemutus selalu transparan & tak bisa diedit
        // dari sisi notifikasi. Read-state memakai dismissal ops, bukan mutasi audit.
        $pengajuanList = RuanganPengajuan::query()
            ->with(['ruangan:id,nama,kode', 'diputusOleh:id,nama_lengkap,role'])
            ->where(fn ($q) => $q->where('pengaju_id', $user->id)
                ->orWhereJsonContains('peserta', $user->id))
            ->whereNotNull('diputus_pada')
            ->orderByDesc('diputus_pada')
            ->limit(30)
            ->get();

        $pgjItems = $pengajuanList->map(function (RuanganPengajuan $p) use ($user) {
            $decider = $p->diputusOleh;
            $deciderName = $decider?->nama_lengkap ?? 'Petugas Lab';
            $deciderRole = $decider?->role === 'dosen' ? 'Dosen' : 'Laboran';
            $jam = substr((string) $p->jam_mulai, 0, 5) . '–' . substr((string) $p->jam_selesai, 0, 5);
            $jadwal = optional($p->tanggal)->translatedFormat('d M Y') . ' · ' . $jam;
            $ruangNama = $p->ruangan?->nama ?? 'Ruangan';
            $status = $p->status;
            $alasan = trim((string) $p->alasan_keputusan);

            [$title, $excerpt, $priority] = match ($status) {
                RuanganPengajuan::STATUS_DISETUJUI => [
                    'Pengajuan ruangan disetujui',
                    "{$ruangNama} · {$jadwal} — sudah bisa dipakai. Disetujui oleh {$deciderName} ({$deciderRole}). Jangan sampai terlewat waktunya!",
                    'penting',
                ],
                RuanganPengajuan::STATUS_DITOLAK => [
                    'Pengajuan ruangan ditolak',
                    "{$ruangNama} · {$jadwal} — ditolak oleh {$deciderName} ({$deciderRole})." . ($alasan ? " Alasan: {$alasan}" : ''),
                    'penting',
                ],
                RuanganPengajuan::STATUS_MENUNGGU_PENERIMAAN => [
                    'Tawaran jadwal pengganti',
                    "{$deciderName} ({$deciderRole}) menawarkan waktu pengganti untuk {$ruangNama} — segera terima atau tolak di halaman pengajuan.",
                    'penting',
                ],
                RuanganPengajuan::STATUS_DIBATALKAN => [
                    'Pengajuan ruangan dibatalkan',
                    "{$ruangNama} · {$jadwal} — dibatalkan oleh {$deciderName} ({$deciderRole})." . ($alasan ? " Alasan: {$alasan}" : ''),
                    'normal',
                ],
                default => [null, null, null],
            };
            if ($title === null) {
                return null;
            }

            $ts = $p->diputus_pada;
            $key = 'pgj-' . $p->id;
            $fp = \App\Support\StaffNotificationService::fingerprint(['id' => $key, 'count' => $ts->timestamp]);

            return [
                'uid' => $key,
                'id' => (int) $p->id,
                'kind' => 'pengajuan',
                'category_label' => 'Keputusan Ruangan',
                'priority' => $priority,
                'title' => $title,
                'excerpt' => \Illuminate\Support\Str::limit($excerpt, 110),
                'body' => $excerpt,
                'meta' => "{$deciderName} · {$deciderRole}",
                'timestamp' => optional($ts)->toIso8601String(),
                'time_human' => optional($ts)->timezone('Asia/Jakarta')->translatedFormat('d M Y, H:i') . ' WIB',
                'is_read' => \App\Support\StaffNotificationService::isDismissed($user, ['id' => $key, 'count' => $ts->timestamp]),
                'read_url' => route('mahasiswa.notifications.ops-read', ['key' => $key, 'fp' => $fp]),
                'action_url' => route('mahasiswa.ruangan', ['tab' => 'riwayat']),
                'action_label' => 'Buka Riwayat Pengajuan',
            ];
        })->filter()->values();

        // Pengingat booking yang segera mulai (≤ 3 jam) — supaya tidak terlewat.
        $reminderItems = $pengajuanList
            ->filter(fn (RuanganPengajuan $p) => $p->status === RuanganPengajuan::STATUS_DISETUJUI
                && $p->mulaiAt()->between(now(), now()->addHours(3)))
            ->map(function (RuanganPengajuan $p) use ($user) {
                $ruangNama = $p->ruangan?->nama ?? 'Ruangan';
                $mulai = substr((string) $p->jam_mulai, 0, 5);
                $sisa = now()->diffInMinutes($p->mulaiAt());
                $excerpt = "{$ruangNama} mulai pukul {$mulai} WIB ({$sisa} menit lagi). Datang tepat waktu — tap kartu/fingerprint di mesin ruangan.";
                $ts = $p->diputus_pada;
                $key = 'pgjr-' . $p->id;
                $fp = \App\Support\StaffNotificationService::fingerprint(['id' => $key, 'count' => $p->mulaiAt()->timestamp]);

                return [
                    'uid' => $key,
                    'id' => (int) $p->id,
                    'kind' => 'pengajuan',
                    'category_label' => 'Pengingat Ruangan',
                    'priority' => 'penting',
                    'title' => 'Penggunaan ruangan segera dimulai',
                    'excerpt' => $excerpt,
                    'body' => $excerpt,
                    'meta' => $ruangNama . ' · ' . $mulai . ' WIB',
                    'timestamp' => optional($ts)->toIso8601String(),
                    'time_human' => 'Mulai ' . $p->mulaiAt()->timezone('Asia/Jakarta')->translatedFormat('d M Y, H:i') . ' WIB',
                    'is_read' => \App\Support\StaffNotificationService::isDismissed($user, ['id' => $key, 'count' => $p->mulaiAt()->timestamp]),
                    'read_url' => route('mahasiswa.notifications.ops-read', ['key' => $key, 'fp' => $fp]),
                    'action_url' => route('mahasiswa.ruangan', ['tab' => 'riwayat']),
                    'action_label' => 'Lihat Detail Pengajuan',
                ];
            })->values();

        $merged = $annItems->concat($replyItems)->concat($pgjItems)->concat($reminderItems)
            ->sortByDesc(fn (array $item) => $item['timestamp'] ?? '')
            ->values();

        $unreadCount = $merged->where('is_read', false)->count();

        $filtered = $filter === 'unread'
            ? $merged->where('is_read', false)->values()
            : $merged;

        $offset = ($page - 1) * $perPage;
        $sliced = $filtered->slice($offset, $perPage)->values()->all();
        $hasMore = $filtered->count() > ($offset + $perPage);

        return [
            'unread_count' => $unreadCount,
            'reply_unread_count' => $replyItems->where('is_read', false)->count(),
            'has_more' => $hasMore,
            'items' => $sliced,
        ];
    }

    /**
     * Asisten lokal cerdas & deterministik untuk mahasiswa SIMALAS.
     * Menjawab secara natural, ramah, dan solutif (digunakan saat offline, testing, atau fallback).
     *
     * @param  array<string, mixed>  $todaySchedule
     */
    /**
     * Deteksi apakah pesan mahasiswa bernada kendala/masalah dan kategori laporannya.
     * Toleran typo umum; kata topik murni (tap/absen/ruangan) tanpa kata masalah tidak dihitung.
     */
    private static function detectIssueKategori(string $message): ?string
    {
        if (! preg_match('/(lapor|lpor|kendala|kndala|keluhan|masal|masaal|koreksi|alfa|tidak lengkap|belum (masuk|tercatat|punya|memiliki|terdaftar|ditugaskan|dapat|ada|terima)|tidak (tercatat|masuk|bisa|ada|punya|menerima|ditugaskan|terdaftar|dapat)|gak bisa|nggak bisa|gbsa|gagal|eror|error|rusak|bermasalah|salah)/i', $message)) {
            return null;
        }
        if (preg_match('/(absen|absensi|kehadiran|tap|finger|sidik jari|alfa|telat|terlambat)/i', $message)) {
            return 'absensi';
        }
        if (preg_match('/(akun|login|password|kata sandi|sandi|email|otp|verifikasi|profil|tim|pbl|proyek|penugasan|anggota|kelompok|manpro|pembimbing)/i', $message)) {
            return 'akun';
        }
        if (preg_match('/(ruangan|ruang|booking|pinjam|proyektor|kunci|pintu|mesin|peralatan|alat|lab)/i', $message)) {
            return 'lainnya';
        }

        return 'saran';
    }

    /** Draft laporan generik saat mahasiswa mengirim gambar tapi analisis visual tidak tersedia. */
    private static function imageReportData(): array
    {
        return [
            'kategori' => 'lainnya',
            'subjek' => 'Kendala Portal — bukti gambar terlampir',
            'pesan' => "Saya mengalami kendala — detail terlihat pada gambar yang saya lampirkan.\n\nMohon bantuan Admin Laboran untuk memeriksanya. Terima kasih.",
        ];
    }

    /** Susun payload draft laporan sesuai kategori kendala (null = bukan kendala). */
    private static function issueReportData(?string $kategori, string $message, array $todaySchedule, ?User $user = null): ?array
    {
        if (! $kategori) {
            return null;
        }
        $hari = $todaySchedule['hari_nama'] ?? 'Hari ini';
        $clean = mb_strimwidth(trim((string) preg_replace('/\s+/', ' ', $message)), 0, 400, '…');

        if ($kategori === 'absensi') {
            return [
                'kategori' => 'absensi',
                'tanggal_absensi' => now('Asia/Jakarta')->toDateString(),
                'referensi_absensi' => 'Sesi ' . $hari,
                'subjek' => 'Kendala Absensi Mahasiswa (' . $hari . ')',
                'pesan' => "Saya mengalami kendala absensi pada sesi {$hari}: \"{$clean}\".\n\n"
                    . 'Mohon bantuan Admin Laboran untuk mencocokkan dengan rekaman log mesin. Terima kasih.',
            ];
        }

        // Sub-topik → subjek & pembuka draft yang spesifik (bukan template generik).
        [$subjekTopik, $pembuka, $penutup] = match (true) {
            (bool) preg_match('/(tim|pbl|proyek|penugasan|anggota|kelompok|manpro|pembimbing)/i', $message) => [
                'Kendala Penugasan Tim PBL',
                $user && ! \App\Support\RuanganBookingService::timMahasiswa($user)
                    ? 'Saya belum terdaftar di tim PBL mana pun. Catatan saya'
                    : 'Saya mengalami kendala terkait tim PBL',
                'Mohon bantuan Admin Laboran untuk memeriksa penugasan tim saya. Terima kasih.',
            ],
            (bool) preg_match('/(kartu|rfid|pin akses|akses|pintu|ruangan?|mesin)/i', $message) => [
                'Kendala Akses Ruangan / Mesin',
                'Saya mengalami kendala akses ruangan atau mesin lab',
                'Mohon bantuan Admin Laboran untuk memeriksa hak akses dan log tap saya. Terima kasih.',
            ],
            (bool) preg_match('/(login|password|sandi|otp|email|verifikasi|terkunci|akun)/i', $message) => [
                'Kendala Akun',
                'Saya mengalami kendala pada akun portal',
                'Mohon bantuan Admin Laboran untuk memeriksa akun saya (reset/kredensial/kunci). Terima kasih.',
            ],
            default => [
                ['akun' => 'Akun', 'lainnya' => 'Fasilitas Lab', 'saran' => 'Umum'][$kategori] ?? 'Umum',
                'Saya mengalami kendala',
                'Mohon bantuan Admin Laboran untuk menindaklanjuti. Terima kasih.',
            ],
        };

        return [
            'kategori' => $kategori,
            'subjek' => "Laporan {$subjekTopik} ({$hari})",
            'pesan' => "{$pembuka}: \"{$clean}\".\n\n{$penutup}",
        ];
    }

    /**
     * Responden lokal terlatih — delegasi ke StudentLocalAssistant
     * (normalisasi slang, scoring intent, jawaban berbasis data nyata).
     */
    private function buildLocalStudentAiReply(User $user, string $message, array $todaySchedule, array $history = []): string
    {
        return \App\Support\StudentLocalAssistant::reply($user, $message, $todaySchedule, $history);
    }

    private function callMobile(Request $request, string $controller, string $method, array $input = [], mixed $routeModel = null): array
    {
        $sub = Request::create('/mahasiswa/internal', 'POST', $input);
        $sub->setUserResolver(fn () => $request->user());
        $sub->attributes->set('mobile_user', $request->user());
        $sub->headers->set('Accept', 'application/json');

        $instance = app($controller);
        $response = $routeModel !== null
            ? $instance->{$method}($sub, $routeModel)
            : $instance->{$method}($sub);

        if (! $response instanceof JsonResponse) {
            return [];
        }

        return $response->getData(true);
    }
}
