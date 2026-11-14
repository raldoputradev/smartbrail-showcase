<?php

namespace App\Http\Controllers;

use App\Models\MesinIot;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class MesinIotController extends Controller
{
    public function index(Request $request)
    {
        $query = MesinIot::query()->where('tipe', '!=', 'loker');

        if ($request->filled('q')) {
            $keyword = trim((string) $request->q);
            $query->where(function ($qb) use ($keyword) {
                $qb->where('id_mesin', 'like', "%{$keyword}%")
                    ->orWhere('nama_mesin', 'like', "%{$keyword}%");
            });
        }

        if ($request->filled('tipe')) {
            $query->where('tipe', $this->normalizeType((string) $request->tipe));
        }

        if ($request->filled('status')) {
            $status = (string) $request->status;
            if ($status === 'aktif') {
                $query->where('is_active', true);
            } elseif ($status === 'nonaktif') {
                $query->where('is_active', false);
            } elseif ($status === 'online') {
                $query->online();
            } elseif ($status === 'offline') {
                $query->where('is_active', true)->where(function ($qb) {
                    $qb->whereNull('last_seen_at')
                        ->orWhere('last_seen_at', '<', now()->subMinutes(MesinIot::ONLINE_THRESHOLD_MINUTES));
                });
            }
        }

        $mesin = $query
            ->orderByRaw('is_active DESC')
            ->orderByRaw('last_seen_at IS NULL')
            ->orderByDesc('last_seen_at')
            ->orderBy('id_mesin')
            ->paginate(15)
            ->withQueryString();

        $newApiKey = session('mesin_plain_key');
        $typeOptions = $this->typeOptions();

        $total = MesinIot::query()->where('tipe', '!=', 'loker')->count();
        $active = MesinIot::query()->where('tipe', '!=', 'loker')->where('is_active', true)->count();
        $online = MesinIot::online()->where('tipe', '!=', 'loker')->count();
        $stats = [
            'total' => $total,
            'active' => $active,
            'inactive' => max(0, $total - $active),
            'online' => $online,
            'offline' => max(0, $active - $online),
            'by_type' => MesinIot::query()
                ->where('tipe', '!=', 'loker')
                ->selectRaw('tipe, COUNT(*) as total')
                ->groupBy('tipe')
                ->pluck('total', 'tipe')
                ->all(),
        ];

        return view('admin.mesin', compact('mesin', 'newApiKey', 'typeOptions', 'stats'));
    }

    public function store(Request $request)
    {
        $request->merge([
            'id_mesin' => Str::upper(trim((string) $request->input('id_mesin', ''))),
            'nama_mesin' => trim((string) $request->input('nama_mesin', '')),
        ]);

        $validated = $request->validate([
            'id_mesin' => ['required', 'string', 'max:100', 'regex:/^[A-Z0-9][A-Z0-9_-]*$/', 'unique:mesin_iot,id_mesin'],
            'nama_mesin' => ['nullable', 'string', 'max:150'],
            'tipe' => ['required', 'string', 'max:50'],
            'tipe_custom' => ['nullable', 'string', 'max:50'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'id_mesin.regex' => 'ID mesin hanya boleh huruf besar, angka, strip (-), atau underscore (_).',
        ]);

        $plainKey = Str::random(40);
        $type = $this->resolveRequestedType($request);
        if ($type === 'loker') {
            return back()->with('error', 'Tipe loker tidak lagi bagian dari produk absensi.')->withInput();
        }
        $namaMesin = $validated['nama_mesin'] !== ''
            ? $validated['nama_mesin']
            : $validated['id_mesin'];

        MesinIot::query()->create([
            'id_mesin' => $validated['id_mesin'],
            'nama_mesin' => $namaMesin,
            'tipe' => $type,
            'api_key' => Hash::make($plainKey),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('admin.mesin.index')
            ->with('mesin_plain_key', $plainKey)
            ->with('mesin_plain_id', $validated['id_mesin'])
            ->with('mesin_plain_name', $namaMesin)
            ->with('mesin_plain_tipe', $type)
            ->with('mesin_setup_mode', 'create')
            ->with('success', "Mesin {$validated['id_mesin']} berhasil ditambahkan.");
    }

    public function update(Request $request, MesinIot $mesin)
    {
        if ($mesin->tipe === 'loker') {
            abort(404);
        }

        $request->validate([
            'nama_mesin' => ['nullable', 'string', 'max:150'],
            'tipe' => ['required', 'string', 'max:50'],
            'tipe_custom' => ['nullable', 'string', 'max:50'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $type = $this->resolveRequestedType($request);
        $payload = [
            'nama_mesin' => $request->input('nama_mesin') ?: $mesin->id_mesin,
            'tipe' => $type,
            'is_active' => $request->boolean('is_active'),
        ];
        if ($type === 'loker') {
            return back()->with('error', 'Tipe loker tidak lagi bagian dari produk absensi.');
        }

        $mesin->update($payload);

        return back()->with('success', "Mesin {$mesin->id_mesin} berhasil diperbarui.");
    }

    public function toggleActive(MesinIot $mesin)
    {
        $mesin->update(['is_active' => !$mesin->is_active]);

        $status = $mesin->is_active ? 'diaktifkan' : 'dinonaktifkan';
        return back()->with('success', "Mesin {$mesin->id_mesin} berhasil {$status}.");
    }

    public function regenerate(MesinIot $mesin)
    {
        $plainKey = Str::random(40);

        $mesin->update([
            'api_key' => Hash::make($plainKey),
        ]);

        return redirect()->route('admin.mesin.index')
            ->with('mesin_plain_key', $plainKey)
            ->with('mesin_plain_id', $mesin->id_mesin)
            ->with('mesin_plain_name', $mesin->nama_mesin ?: $mesin->id_mesin)
            ->with('mesin_plain_tipe', $mesin->tipe)
            ->with('mesin_setup_mode', 'regenerate')
            ->with('success', "API key mesin {$mesin->id_mesin} berhasil di-generate ulang.");
    }

    public function destroy(MesinIot $mesin)
    {
        $idMesin = $mesin->id_mesin;
        $mesin->delete();

        return back()->with('success', "Mesin {$idMesin} berhasil dihapus.");
    }

    private function typeOptions(): array
    {
        $base = [
            'absensi' => 'Absensi',
        ];

        $existing = MesinIot::query()
            ->select('tipe')
            ->whereNotNull('tipe')
            ->distinct()
            ->pluck('tipe')
            ->filter()
            ->mapWithKeys(fn ($type) => [$type => Str::headline(str_replace(['-', '_'], ' ', $type))])
            ->all();

        return $base + $existing;
    }

    private function resolveRequestedType(Request $request): string
    {
        $type = (string) $request->input('tipe', 'absensi');
        if ($type === '__custom') {
            $type = (string) $request->input('tipe_custom', '');
        }

        return $this->normalizeType($type);
    }

    private function normalizeType(string $type): string
    {
        $normalized = Str::of($type)
            ->lower()
            ->replace([' ', '.'], '_')
            ->replaceMatches('/[^a-z0-9_-]/', '')
            ->trim('_-')
            ->toString();

        return $normalized !== '' ? $normalized : 'absensi';
    }

}
