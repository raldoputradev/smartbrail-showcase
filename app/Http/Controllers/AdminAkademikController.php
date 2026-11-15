<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AdminShellProps;
use App\Models\AcademicPeriod;
use App\Models\User;
use App\Support\AdminAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AdminAkademikController extends Controller
{
    use AdminShellProps;

    private const STATUS = ['aktif', 'cuti', 'mengulang', 'lulus', 'keluar'];

    public function index(Request $request)
    {
        \Inertia\Inertia::setRootView('app-admin');
        $user = $request->user();

        $periods = AcademicPeriod::orderByDesc('tanggal_mulai')->get()
            ->map(fn (AcademicPeriod $p) => [
                'id' => $p->id,
                'kode' => $p->kode,
                'label' => $p->label,
                'jenis' => $p->jenis,
                'tanggal_mulai' => $p->tanggal_mulai->toDateString(),
                'tanggal_selesai' => $p->tanggal_selesai->toDateString(),
                'is_active' => $p->is_active,
            ])->values()->all();

        return \Inertia\Inertia::render('Akademik', [
            'menu' => $this->adminSidebarMenu($user),
            'notif' => $this->adminNotifications($user),
            'periods' => $periods,
            'preview' => $this->kenaikanPreview(),
            'urls' => [
                'index' => route('admin.akademik'),
                'periode' => route('admin.akademik.periode.store'),
                'periode_update' => route('admin.akademik.periode.update', ['period' => '__ID__']),
                'periode_delete' => route('admin.akademik.periode.destroy', ['period' => '__ID__']),
                'aktivasi' => route('admin.akademik.periode.activate', ['period' => '__ID__']),
                'kenaikan' => route('admin.akademik.kenaikan'),
            ],
        ]);
    }

    public function storePeriod(Request $request)
    {
        $validated = $request->validate([
            'kode' => ['required', 'string', 'max:40', 'unique:academic_periods,kode'],
            'label' => ['nullable', 'string', 'max:120'],
            'jenis' => ['required', Rule::in(AcademicPeriod::JENIS)],
            'tanggal_mulai' => ['required', 'date'],
            'tanggal_selesai' => ['required', 'date', 'after:tanggal_mulai'],
        ]);

        // Maju saja — periode baru tidak boleh mundur dari periode terakhir
        // yang sudah ada; bila belum ada periode sama sekali, tidak boleh
        // membuat periode yang sudah berakhir (ikuti kalender berjalan).
        $latest = AcademicPeriod::orderByDesc('tanggal_mulai')->first();
        if ($latest && $validated['tanggal_mulai'] <= $latest->tanggal_mulai) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'kode' => "Tidak bisa membuat periode mundur — lanjutkan setelah {$latest->label}.",
            ]);
        }
        if (!$latest && $validated['tanggal_selesai'] < now()->toDateString()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'kode' => 'Periode yang sudah berakhir tidak bisa dibuat — mulai dari semester yang sedang berjalan.',
            ]);
        }

        // Label opsional — bila kosong, turunkan dari kode "TAHUN-jenis".
        if (!filled($validated['label'] ?? null)) {
            if (preg_match('/^(\d{4})-(ganjil|genap)$/', $validated['kode'], $m)) {
                $validated['label'] = ucfirst($m[2]) . ' ' . $m[1] . '/' . ((int) $m[1] + 1);
            } else {
                $validated['label'] = $validated['kode'];
            }
        }

        $p = AcademicPeriod::create($validated);
        AdminAudit::catat('akademik.periode', $p->label,
            "buat periode {$p->kode} ({$p->jenis} {$p->tanggal_mulai->toDateString()} s/d {$p->tanggal_selesai->toDateString()})");

        return back()->with('success', "Periode {$p->label} dibuat.");
    }

    public function updatePeriod(Request $request, AcademicPeriod $period)
    {
        $validated = $request->validate([
            'kode' => ['required', 'string', 'max:40', Rule::unique('academic_periods', 'kode')->ignore($period->id)],
            'label' => ['nullable', 'string', 'max:120'],
            'jenis' => ['required', Rule::in(AcademicPeriod::JENIS)],
            'tanggal_mulai' => ['required', 'date'],
            'tanggal_selesai' => ['required', 'date', 'after:tanggal_mulai'],
        ]);

        if (!filled($validated['label'] ?? null)) {
            if (preg_match('/^(\d{4})-(ganjil|genap)$/', $validated['kode'], $m)) {
                $validated['label'] = ucfirst($m[2]) . ' ' . $m[1] . '/' . ((int) $m[1] + 1);
            } else {
                $validated['label'] = $validated['kode'];
            }
        }

        $period->update($validated);
        AdminAudit::catat('akademik.periode', $period->label,
            "ubah periode {$period->kode} ({$period->tanggal_mulai->toDateString()} s/d {$period->tanggal_selesai->toDateString()})");

        return back()->with('success', "Periode {$period->label} diperbarui.");
    }

    public function destroyPeriod(AcademicPeriod $period)
    {
        if ($period->is_active) {
            return back()->with('error', 'Periode yang sedang aktif tidak bisa dihapus — aktifkan periode lain terlebih dahulu.');
        }
        $label = $period->label;
        $period->delete();
        AdminAudit::catat('akademik.periode', $label, 'menghapus periode akademik');

        return back()->with('success', "Periode {$label} dihapus.");
    }

    public function activatePeriod(Request $request, AcademicPeriod $period)
    {
        DB::transaction(function () use ($period) {
            AcademicPeriod::where('is_active', true)->update(['is_active' => false]);
            $period->update(['is_active' => true]);
        });
        AdminAudit::catat('akademik.aktivasi', $period->label, 'menjadikan periode aktif');

        return back()->with('success', "Periode aktif sekarang: {$period->label}.");
    }

    /**
     * Terapkan kenaikan semester + perubahan status akademik dalam satu
     * aksi teraudit. Mahasiswa cuti/mengulang ditandai lewat status_updates
     * dan otomatis dikecualikan dari ids yang naik.
     */
    public function applyKenaikan(Request $request)
    {
        $validated = $request->validate([
            'ids' => ['sometimes', 'array', 'max:5000'],
            'ids.*' => ['integer', 'exists:users,id'],
            'status_updates' => ['sometimes', 'array', 'max:5000'],
            'status_updates.*' => [Rule::in(self::STATUS)],
        ]);

        $ids = collect($validated['ids'] ?? [])->map(fn ($i) => (int) $i)->unique();
        $statusUpdates = collect($validated['status_updates'] ?? [])
            ->map(fn ($s) => (string) $s);

        $naik = 0;
        $lulus = 0;
        $statusCount = 0;

        DB::transaction(function () use ($ids, $statusUpdates, &$naik, &$lulus, &$statusCount) {
            foreach ($statusUpdates as $uid => $status) {
                $affected = User::where('id', (int) $uid)
                    ->where('role', 'mahasiswa')
                    ->update(['academic_status' => $status]);
                $statusCount += $affected;
            }

            // Yang statusnya baru diubah jadi non-aktif tidak ikut naik.
            $students = User::whereIn('id', $ids->all())
                ->where('role', 'mahasiswa')
                ->get();

            foreach ($students as $s) {
                $effStatus = (string) ($statusUpdates[$s->id] ?? $s->academic_status ?? 'aktif');
                if ($effStatus !== 'aktif') {
                    continue;
                }
                $sem = (int) ($s->semester ?? 0);
                if ($sem < 1) {
                    continue;
                }
                if ($sem >= 8) {
                    $s->update(['academic_status' => 'lulus']);
                    $lulus++;
                    continue;
                }
                $sem++;
                $s->update([
                    'semester' => $sem,
                    'semester_type' => $sem % 2 === 1 ? 'ganjil' : 'genap',
                ]);
                $naik++;
            }
        });

        AdminAudit::catat('akademik.kenaikan', null,
            "{$naik} mahasiswa naik semester, {$lulus} ditandai lulus, {$statusCount} perubahan status");

        return back()->with('success',
            "{$naik} mahasiswa naik semester" . ($lulus ? ", {$lulus} ditandai lulus" : '')
            . ($statusCount ? ", {$statusCount} status diperbarui" : '') . '.');
    }

    /**
     * Kelompok mahasiswa untuk layar review: usul naik (aktif), ditahan
     * (cuti/mengulang), dan di luar kenaikan (lulus/keluar).
     */
    private function kenaikanPreview(): array
    {
        $students = User::where('role', 'mahasiswa')
            ->where('is_active', true)
            ->orderBy('angkatan')->orderBy('kelas')->orderBy('nama_lengkap')
            ->get(['id', 'nama_lengkap', 'nitk_nidn', 'kelas', 'tim', 'angkatan',
                   'semester', 'semester_type', 'academic_status']);

        $naik = $students->filter(fn ($s) => ($s->academic_status ?? 'aktif') === 'aktif')
            ->map(fn ($s) => [
                'id' => $s->id,
                'nama' => $s->nama_lengkap,
                'nim' => $s->nitk_nidn,
                'kelas' => $s->kelas,
                'tim' => $s->tim,
                'angkatan' => $s->angkatan,
                'semester' => $s->semester,
                'semester_type' => $s->semester_type,
                'usulan' => ((int) ($s->semester ?? 0)) >= 8 ? 'lulus' : 'naik',
            ])->values()->all();

        $tahan = $students->filter(fn ($s) => in_array($s->academic_status ?? 'aktif', ['cuti', 'mengulang'], true))
            ->map(fn ($s) => [
                'id' => $s->id,
                'nama' => $s->nama_lengkap,
                'nim' => $s->nitk_nidn,
                'kelas' => $s->kelas,
                'angkatan' => $s->angkatan,
                'semester' => $s->semester,
                'status' => $s->academic_status,
            ])->values()->all();

        $selesai = $students->filter(fn ($s) => in_array($s->academic_status ?? 'aktif', ['lulus', 'keluar'], true))
            ->count();

        return [
            'naik' => $naik,
            'tahan' => $tahan,
            'selesai_count' => $selesai,
            'status_options' => self::STATUS,
        ];
    }
}
