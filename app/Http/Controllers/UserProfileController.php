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

    public function show(Request $request, User $user)

    {

        $viewer = $request->user();

        if (!$viewer) {

            abort(403, 'Anda tidak memiliki akses untuk melihat profil ini.');

        }

        if ($viewer->role === 'mahasiswa' && $viewer->id !== $user->id) {

            abort(403, 'Anda tidak memiliki akses untuk melihat profil ini.');

        }

        if (!in_array($viewer->role, ['laboran', 'dosen', 'mahasiswa'], true)) {

            abort(403, 'Anda tidak memiliki akses untuk melihat profil ini.');

        }



        $user->load(['approvedBy:id,nama_lengkap', 'programStudi']);



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

        $recentLoker = collect();



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

        $canUploadPhoto = $isOwnProfile && in_array($viewer->role, ['laboran', 'dosen', 'mahasiswa'], true);



        return view('profiles.show', [

            'profileUser' => $user,

            'accountOrigin' => $accountOrigin,

            'approvalBadge' => $approvalBadge,

            'pblProjects' => $pblProjects,

            'approvalTimeline' => $approvalTimeline,

            'stats' => $stats,

            'recentAbsensi' => $recentAbsensi,

            'recentLoker' => $recentLoker,

            'canUploadPhoto' => $canUploadPhoto,

            'isStaffProfile' => $user->isDosen() || $user->isLaboran(),

        ]);

    }



    public function updateOwnPhoto(Request $request)

    {

        $user = $request->user();

        if (!$user || !in_array($user->role, ['laboran', 'dosen', 'mahasiswa'], true)) {

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

        if (!$user || !in_array($user->role, ['laboran', 'dosen', 'mahasiswa'], true)) {

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

}


