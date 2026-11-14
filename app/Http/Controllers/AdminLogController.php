<?php

namespace App\Http\Controllers;

use App\Models\LogAbsensi;
use App\Models\MesinIot;
use App\Support\AbsensiSnapshot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminLogController extends Controller
{
    public function absensi(Request $request): View
    {
        return view('admin.log_absensi', $this->absensiState($request));
    }

    public function snapshot(Request $request): JsonResponse
    {
        $state = $this->absensiState($request);
        $logs = $state['logs']->withPath(route('admin.log.absensi'));
        $rows = collect($logs->items())->map(function (LogAbsensi $log): array {
            return [
                'waktu' => optional($log->waktu_tap)->timezone('Asia/Jakarta')->toDateTimeString(),
                'nama' => $log->user->nama_lengkap ?? '',
                'nim' => $log->user->nitk_nidn ?? '',
                'role' => $log->user->role ?? '',
                'id_mesin' => $log->id_mesin,
            ];
        })->all();
        $stats = $state['stats'];

        return response()->json(AbsensiSnapshot::envelope('log', 'laboran', [
            'query' => AbsensiSnapshot::canonicalQuery($request),
            'revision' => substr(hash('sha256', json_encode([$rows, $stats])), 0, 16),
            'summary' => $stats,
            'summary_text' => [
                'total' => (string) $stats['total'],
                'today' => (string) $stats['today'],
                'minggu' => (string) $stats['minggu_ini'],
                'mesin' => (string) $state['mesinList']->count(),
            ],
            'pagination' => [
                'page' => $logs->currentPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
                'last_page' => $logs->lastPage(),
            ],
            'rows' => $rows,
            'regions' => [
                'rows' => view('admin._log_absensi_rows', ['logs' => $logs])->render(),
                'pager' => $logs->links()->toHtml(),
            ],
        ]));
    }

    /**
     * @return array{logs: \Illuminate\Contracts\Pagination\LengthAwarePaginator, stats: array<string, int>, filter: array<string, string>, mesinList: \Illuminate\Support\Collection}
     */
    private function absensiState(Request $request): array
    {
        $filter = [
            'q' => trim((string) $request->query('q', '')),
            'id_mesin' => (string) $request->query('id_mesin', ''),
            'tanggal_mulai' => (string) $request->query('tanggal_mulai', ''),
            'tanggal_akhir' => (string) $request->query('tanggal_akhir', ''),
        ];

        $today = now()->toDateString();
        if ($filter['tanggal_mulai'] === '' && $filter['tanggal_akhir'] === '') {
            $filter['tanggal_mulai'] = $today;
            $filter['tanggal_akhir'] = $today;
        }

        $query = LogAbsensi::query()->with(['user:id,nama_lengkap,nitk_nidn,role,profile_photo_path']);

        if ($filter['id_mesin'] !== '') {
            $query->where('id_mesin', $filter['id_mesin']);
        }
        if ($filter['tanggal_mulai'] !== '') {
            $query->whereDate('waktu_tap', '>=', $filter['tanggal_mulai']);
        }
        if ($filter['tanggal_akhir'] !== '') {
            $query->whereDate('waktu_tap', '<=', $filter['tanggal_akhir']);
        }
        if ($filter['q'] !== '') {
            $kw = $filter['q'];
            $query->whereHas('user', function ($qb) use ($kw) {
                $qb->where('nama_lengkap', 'like', "%{$kw}%")
                    ->orWhere('nitk_nidn', 'like', "%{$kw}%");
            });
        }

        $logs = $query->orderBy('waktu_tap')->paginate(20)->withQueryString();

        $stats = [
            'total' => LogAbsensi::count(),
            'today' => LogAbsensi::whereDate('waktu_tap', now()->toDateString())->count(),
            'minggu_ini' => LogAbsensi::whereBetween('waktu_tap', [now()->startOfWeek(), now()->endOfWeek()])->count(),
        ];

        $mesinList = MesinIot::query()->where('tipe', 'absensi')->orderBy('id_mesin')->get(['id_mesin', 'nama_mesin']);

        return compact('logs', 'stats', 'filter', 'mesinList');
    }
}
