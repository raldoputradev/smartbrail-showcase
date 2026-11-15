<?php



namespace App\Http\Controllers;



use App\Models\LogAbsensi;


use App\Models\User;

use App\Support\AccountOrigin;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\File;

use Illuminate\Support\Str;



class UserProfileController extends Controller

{

    /**
     * Kartu profil ringkas (JSON) untuk popup — hanya field non-sensitif.
     * Akses: admin/laboran/dosen boleh lihat siapa pun; mahasiswa hanya
     * sesama anggota tim PBL-nya atau dirinya sendiri.
     */
    public function ringkas(Request $request, User $user): \Illuminate\Http\JsonResponse
    {
        $viewer = $request->user();
        if (! $viewer || ! in_array($viewer->role, ['admin', 'laboran', 'dosen', 'mahasiswa'], true)) {
            abort(403);
        }

        if ($viewer->role === 'mahasiswa' && $viewer->id !== $user->id) {
            $sameTim = filled($viewer->tim) && $viewer->tim === $user->tim;
            if (! $sameTim) {
                abort(403);
            }
        }

        $user->loadMissing('programStudi:id,nama');

        $roleLabel = [
            'admin' => 'Administrator', 'laboran' => 'Laboran',
            'dosen' => 'Dosen', 'mahasiswa' => 'Mahasiswa',
        ][$user->role] ?? ucfirst((string) $user->role);

        $profil = [
            'nama' => $user->nama_lengkap,
            'identitas' => $user->nitk_nidn,
            'role' => $roleLabel,
            'kelas' => $user->kelas,
            'prodi' => $user->programStudi?->nama,
            'semester' => $user->semester,
            'tim' => $user->role === 'mahasiswa' ? $user->tim : null,
            'shift' => $user->shift,
            'foto' => $user->profile_photo_url ?? null,
            'status' => $user->is_active ? 'Aktif' : 'Nonaktif',
            'terdaftar' => $user->created_at?->format('d M Y'),
        ];

        // Status online/aktivitas terakhir hanya untuk admin — bukan role lain.
        if ($viewer->role === 'admin') {
            $profil['online'] = $user->isOnline();
            $profil['last_seen'] = $user->last_seen_label;
        }

        return response()->json(['ok' => true, 'profil' => $profil]);
    }

    public function show(Request $request, User $user)

    {

        $viewer = $request->user();

        if (!$viewer) {

            abort(403, 'Anda tidak memiliki akses untuk melihat profil ini.');

        }

        if ($viewer->role === 'mahasiswa' && $viewer->id !== $user->id) {

            abort(403, 'Anda tidak memiliki akses untuk melihat profil ini.');

        }

        if (!in_array($viewer->role, ['admin', 'laboran', 'dosen', 'mahasiswa'], true)) {

            abort(403, 'Anda tidak memiliki akses untuk melihat profil ini.');

        }



        $user->load(['approvedBy:id,nama_lengkap', 'programStudi']);

        // Modal OTP verifikasi email tersedia di profil sendiri (banner strip
        // disupres via email_verify_optional — verifikasi lewat tombol di
        // kartu Status Akun, tanpa pemaksaan).
        if ($viewer->id === $user->id && ! $user->email_verified_at) {
            $request->session()->now('email_verification_pending', true);
            $request->session()->now('account_verify_needs_email', blank($user->email));
            $request->session()->now('account_verify_user_id', $user->id);
            $request->session()->now('email_verify_optional', true);
        }



        $accountOrigin = AccountOrigin::resolve($user);

        $approvalBadge = AccountOrigin::approvalBadge($user);

        $pblProjects = AccountOrigin::pblProjectsForDosen($user);



        $approvalTimeline = $user->approvalLogs()

            ->with('actor:id,nama_lengkap')

            ->latest()

            ->limit(8)

            ->get();



        $stats = null;

        $recentAbsensi = collect();



        if ($user->role === 'mahasiswa') {

            $stats = [

                'absensi' => LogAbsensi::query()->where('user_id', $user->id)->count(),

                'last_absensi' => LogAbsensi::query()->where('user_id', $user->id)->latest('waktu_tap')->first(),

            ];



            $recentAbsensi = LogAbsensi::query()

                ->where('user_id', $user->id)

                ->latest('waktu_tap')

                ->limit(6)

                ->get();



        }



        $isOwnProfile = $viewer->id === $user->id;

        $canUploadPhoto = $isOwnProfile && in_array($viewer->role, ['admin', 'laboran', 'dosen', 'mahasiswa'], true);



        return view('profiles.show', [

            'profileUser' => $user,

            'accountOrigin' => $accountOrigin,

            'approvalBadge' => $approvalBadge,

            'pblProjects' => $pblProjects,

            'approvalTimeline' => $approvalTimeline,

            'stats' => $stats,

            'recentAbsensi' => $recentAbsensi,

            'canUploadPhoto' => $canUploadPhoto,

            'isOwnProfile' => $isOwnProfile,

            'isStaffProfile' => $user->isDosen() || $user->isLaboran(),

            'presence' => $viewer->role === 'admin' ? [
                'online' => $user->isOnline(),
                'last_seen' => $user->last_seen_label,
            ] : null,

        ]);

    }



    public function updateOwnPhoto(Request $request)

    {

        $user = $request->user();

        if (!$user || !in_array($user->role, ['admin', 'laboran', 'dosen', 'mahasiswa'], true)) {

            abort(403, 'Anda tidak memiliki akses untuk mengganti foto profil.');

        }



        $validated = $request->validate([

            'profile_photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],

        ]);



        $file = $validated['profile_photo'];

        $directory = public_path('uploads/profile-photos');

        if (!File::isDirectory($directory)) {

            File::makeDirectory($directory, 0755, true);

        }



        if ($user->profile_photo_path && str_starts_with($user->profile_photo_path, 'uploads/profile-photos/')) {

            File::delete(public_path($user->profile_photo_path));

        }



        $filename = 'user-' . $user->id . '-' . Str::random(10) . '.' . $file->getClientOriginalExtension();

        $file->move($directory, $filename);



        $user->forceFill([

            'profile_photo_path' => 'uploads/profile-photos/' . $filename,

        ])->save();



        return back()->with('success', 'Foto profil berhasil diperbarui.');

    }



    public function destroyOwnPhoto(Request $request)

    {

        $user = $request->user();

        if (!$user || !in_array($user->role, ['admin', 'laboran', 'dosen', 'mahasiswa'], true)) {

            abort(403, 'Anda tidak memiliki akses untuk menghapus foto profil.');

        }



        if ($user->profile_photo_path && str_starts_with($user->profile_photo_path, 'uploads/profile-photos/')) {

            File::delete(public_path($user->profile_photo_path));

        }



        $user->forceFill([

            'profile_photo_path' => null,

        ])->save();



        return back()->with('success', 'Foto profil berhasil dihapus. Avatar kembali ke default.');

    }

    /**
     * Pengguna memperbarui nomor HP-nya sendiri dari halaman profil.
     * Email tetap lewat alur OTP (account-email.post), bukan di sini.
     */
    public function updateOwnContact(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            abort(403);
        }

        $validated = $request->validate([
            'no_hp' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s()]{8,30}$/'],
        ], [
            'no_hp.regex' => 'Nomor HP harus 8–30 karakter berisi angka (boleh +, spasi, -, atau kurung).',
        ]);

        $user->forceFill(['no_hp' => $validated['no_hp'] ?: null])->save();

        return back()->with('success', 'Nomor HP berhasil diperbarui.');
    }

    /**
     * Staf (admin/laboran/dosen) mengganti PIN akses ruangan mereka sendiri.
     * PIN permanen — berlaku selama akun aktif; mahasiswa memakai PIN per-izin.
     */
    public function updateOwnAccessPin(Request $request)
    {
        $user = $request->user();

        if (!$user || ! in_array($user->role, ['admin', 'laboran', 'dosen'], true)) {
            abort(403);
        }

        $validated = $request->validate([
            'akses_pin' => ['required', 'string', 'digits:6'],
        ], [
            'akses_pin.digits' => 'PIN akses harus tepat 6 digit angka.',
        ]);

        $user->forceFill(['akses_pin' => \Illuminate\Support\Facades\Hash::make($validated['akses_pin'])])->save();

        return back()->with('success', 'PIN akses ruangan berhasil diperbarui.');
    }

}


