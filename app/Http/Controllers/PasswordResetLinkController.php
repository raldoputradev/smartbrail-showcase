<?php

namespace App\Http\Controllers;

use App\Http\Concerns\ValidatesRecaptcha;
use App\Models\User;
use App\Rules\StrongPassword;
use App\Support\AccountActivation;
use App\Support\AccountEmailVerification;
use App\Support\AsyncMailer;
use App\Support\PasswordResetLink;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Pemulihan password via tautan email sekali pakai (token acak, terbatas waktu, terikat akun).
 * Respons permintaan tidak membocorkan ada/tidaknya akun.
 */
class PasswordResetLinkController extends Controller
{
    use ValidatesRecaptcha;

    /**
     * POST /forgot-password — terima NIM atau email terdaftar.
     * Email hanya dikirim ke alamat yang sah terikat pada akun, bukan alamat bebas.
     */
    public function sendLink(Request $request)
    {
        PasswordResetLink::pruneExpired();

        $request->validate([
            'identifier' => ['required', 'string', 'max:255'],
        ], [
            'identifier.required' => 'Masukkan NIM atau username akun Anda.',
        ]);

        if ($redirect = $this->validateRecaptcha($request, 'forgot')) {
            return $redirect;
        }

        $identifier = trim((string) $request->input('identifier'));
        $user = $this->findByIdentifier($identifier);

        // Cooldown 60 detik per email akun (kirim hanya ke email terikat).
        if ($user && filled($user->email)) {
            $email = AccountEmailVerification::normalizeEmail($user->email);
            $lastToken = DB::table('email_verification_tokens')
                ->whereRaw('LOWER(email) = ?', [$email])
                ->where('type', PasswordResetLink::TYPE)
                ->where('token', '!=', '')
                ->orderByDesc('created_at')
                ->first();

            $canSend = ! $lastToken
                || now()->diffInSeconds(\Carbon\Carbon::parse($lastToken->created_at), true) >= 60;

            if ($canSend) {
                $plain = PasswordResetLink::issue($user);
                $url = route('password.reset.form', ['token' => $plain]);
                AsyncMailer::dispatchPasswordResetLink(
                    $user->email,
                    $url,
                    (string) $user->nama_lengkap,
                    PasswordResetLink::TTL_MINUTES
                );
            }
        }

        return redirect()->route('login')
            ->with('auth_panel', 'forgot')
            ->with('reset_link_sent', true)
            ->with('success', 'Tautan "Atur Ulang Password" sudah dikirim ke email yang terikat pada akun Anda. Periksa kotak masuk (dan folder spam).');
    }

    /**
     * POST /forgot-password/cek-email — petunjuk email terikat (disamarkan)
     * untuk konfirmasi pemilik sebelum tautan dikirim. Di-throttle per-IP.
     */
    public function cekEmail(Request $request)
    {
        $request->validate(['identifier' => 'required|string|max:255']);

        $user = $this->findByIdentifier(trim((string) $request->input('identifier')));

        if (! $user) {
            return response()->json(['ok' => true, 'state' => 'no_account']);
        }
        if (! filled($user->email)) {
            return response()->json(['ok' => true, 'state' => 'no_email']);
        }

        return response()->json([
            'ok' => true,
            'state' => 'found',
            'email_masked' => self::maskEmail((string) $user->email),
        ]);
    }

    /** viona.syafitri@gmail.com → v••••••••ri@gmail.com */
    private static function maskEmail(string $email): string
    {
        $parts = explode('@', $email, 2);
        $local = $parts[0];
        $domain = $parts[1] ?? '';
        $len = mb_strlen($local);

        if ($len <= 2) {
            $masked = str_repeat('•', $len);
        } else {
            $masked = mb_substr($local, 0, 1) . str_repeat('•', max(3, $len - 3)) . mb_substr($local, -2);
        }

        return $masked . '@' . $domain;
    }

    /** GET /atur-ulang-password/{token} — halaman khusus password baru + konfirmasi. */
    public function showForm(Request $request, string $token)
    {
        $resolved = PasswordResetLink::resolve($token);

        $accountRole = $resolved['user']->role ?? null;
        $roleLabel = match ($accountRole) {
            'admin', 'laboran' => 'Admin Laboran',
            'dosen' => 'Dosen',
            'mahasiswa' => 'Mahasiswa',
            default => null,
        };

        return response()->view('auth.reset-password', [
            'token' => $token,
            'tokenValid' => $resolved !== null,
            'accountName' => $resolved['user']->nama_lengkap ?? null,
            'accountRole' => $accountRole,
            'roleLabel' => $roleLabel,
        ])->header('Cache-Control', 'no-store, private');
    }

    /** POST /atur-ulang-password — password berubah hanya setelah token valid & simpan berhasil. */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:100'],
            'password' => ['required', 'string', 'max:128', 'confirmed', new StrongPassword],
        ], [
            'password.required' => 'Password baru wajib diisi.',
            'password.confirmed' => 'Konfirmasi password baru tidak cocok.',
        ]);

        $resolved = PasswordResetLink::resolve($validated['token']);
        if (! $resolved) {
            return back()->withErrors([
                'token' => 'Tautan reset tidak valid, sudah dipakai, atau kedaluwarsa. Silakan minta tautan baru dari halaman Lupa Password.',
            ]);
        }

        /** @var User $user */
        $user = $resolved['user'];

        DB::transaction(function () use ($user, $validated) {
            $payload = [
                'password' => $validated['password'],
                'remember_token' => Str::random(60),
                'mobile_api_token' => null,
                'mobile_token_expires_at' => null,
                // Kepemilikan email terbukti melalui tautan pada email terikat akun.
                'email_verified_at' => $user->email_verified_at ?? now(),
            ];
            if (Schema::hasColumn('users', 'must_change_password')) {
                $payload['must_change_password'] = false;
            }
            foreach (['failed_login_attempts' => 0, 'login_locked_until' => null, 'login_requires_admin_unlock' => false, 'login_suspicious_at' => null] as $col => $val) {
                if (Schema::hasColumn('users', $col)) {
                    $payload[$col] = $val;
                }
            }

            $user->forceFill($payload)->save();
        });

        AccountActivation::revokeActiveTokensAndSessions($user);
        PasswordResetLink::consume((int) $resolved['record']->id);
        AccountActivation::log($user, null, $request, 'password_reset_link',
            'Password diatur ulang melalui tautan pemulihan email sekali pakai; sesi & token lama dicabut.');

        return redirect()->route('password.reset.done');
    }

    /** Halaman sukses setelah reset — tidak menampilkan data akun. */
    public function done()
    {
        return response()->view('auth.reset-password', [
            'token' => null,
            'tokenValid' => null,
            'done' => true,
        ])->header('Cache-Control', 'no-store, private');
    }

    /**
     * POST /mahasiswa/password/kirim-tautan-reset — mahasiswa login minta tautan ke email akun sendiri.
     * Tidak memerlukan persetujuan admin; tanpa email terikat → diarahkan ke laboran.
     */
    public function sendLinkForSelf(Request $request)
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user && in_array($user->role, ['admin', 'laboran', 'dosen', 'mahasiswa'], true), 403);

        if (! filled($user->email)) {
            return back()->withErrors([
                'email' => 'Akun Anda belum memiliki email terikat. Minta reset password sementara melalui admin/laboran.',
            ]);
        }

        $email = AccountEmailVerification::normalizeEmail($user->email);
        $lastToken = DB::table('email_verification_tokens')
            ->whereRaw('LOWER(email) = ?', [$email])
            ->where('type', PasswordResetLink::TYPE)
            ->where('token', '!=', '')
            ->orderByDesc('created_at')
            ->first();

        if ($lastToken && now()->diffInSeconds(\Carbon\Carbon::parse($lastToken->created_at), true) < 60) {
            $remaining = (int) ceil(60 - now()->diffInSeconds(\Carbon\Carbon::parse($lastToken->created_at), true));

            return back()->with('info', "Tautan pemulihan baru saja dikirim. Tunggu {$remaining} detik untuk meminta ulang.");
        }

        $plain = PasswordResetLink::issue($user);
        AsyncMailer::dispatchPasswordResetLink(
            $user->email,
            route('password.reset.form', ['token' => $plain]),
            (string) $user->nama_lengkap,
            PasswordResetLink::TTL_MINUTES
        );

        return back()->with('success', "Tautan \"Atur Ulang Password\" dikirim ke {$user->email}. Buka email tersebut untuk membuat password baru.");
    }

    private function findByIdentifier(string $identifier): ?User
    {
        if ($identifier === '') {
            return null;
        }

        if (str_contains($identifier, '@')) {
            return User::query()->whereRaw('LOWER(email) = ?', [strtolower($identifier)])->first();
        }

        return User::query()->whereRaw('LOWER(nitk_nidn) = ?', [mb_strtolower($identifier)])->first();
    }
}
