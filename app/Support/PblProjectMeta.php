<?php

namespace App\Support;

use App\Models\PblProject;
use Illuminate\Support\Facades\Storage;

/**
 * Metadata ringan proyek PBL (foto, deskripsi, progress) yang diatur
 * oleh manpro/co-manpro — disimpan sebagai JSON + file di disk public
 * agar tidak perlu mengubah skema tabel pbl_projects.
 */
class PblProjectMeta
{
    private const FILE = 'pbl_meta.json';
    private const DIR = 'pbl';

    /**
     * @return array{foto: ?string, deskripsi: ?string, progress: ?int, status: ?string}
     */
    public static function get(int $projectId): array
    {
        $all = self::all();
        $m = $all[$projectId] ?? [];

        return [
            'foto' => isset($m['foto']) ? asset('storage/'.$m['foto']) : null,
            'deskripsi' => $m['deskripsi'] ?? null,
            'progress' => isset($m['progress']) ? max(0, min(100, (int) $m['progress'])) : null,
            'status' => $m['status'] ?? null,
            'tahun_ajaran' => $m['tahun_ajaran'] ?? null,
            'kategori' => $m['kategori'] ?? null,
            'semester' => $m['semester'] ?? null,
            'periode_mulai' => $m['periode_mulai'] ?? null,
            'periode_selesai' => $m['periode_selesai'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function put(int $projectId, array $data): void
    {
        $all = self::all();
        $m = $all[$projectId] ?? [];
        foreach (['deskripsi', 'progress', 'status', 'tahun_ajaran', 'kategori', 'semester', 'periode_mulai', 'periode_selesai'] as $key) {
            if (array_key_exists($key, $data)) {
                $m[$key] = $data[$key];
            }
        }
        $all[$projectId] = $m;
        Storage::disk('local')->put(self::FILE, json_encode($all, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    /** Simpan file foto proyek ke disk public → path relatif (pbl/{id}.ext). */
    public static function storeFoto(int $projectId, \Illuminate\Http\UploadedFile $file): string
    {
        // Hapus foto lama (ekstensi apa pun) agar tidak menumpuk.
        foreach (Storage::disk('public')->files(self::DIR) as $old) {
            if (preg_match('#^'.self::DIR.'/'.$projectId.'\.#', $old)) {
                Storage::disk('public')->delete($old);
            }
        }
        $ext = strtolower($file->getClientOriginalExtension() ?: 'jpg');
        $path = $file->storeAs(self::DIR, $projectId.'.'.$ext, 'public');

        $all = self::all();
        $m = $all[$projectId] ?? [];
        $m['foto'] = $path;
        $all[$projectId] = $m;
        Storage::disk('local')->put(self::FILE, json_encode($all, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return $path;
    }

    public static function removeFoto(int $projectId): void
    {
        foreach (Storage::disk('public')->files(self::DIR) as $old) {
            if (preg_match('#^'.self::DIR.'/'.$projectId.'\.#', $old)) {
                Storage::disk('public')->delete($old);
            }
        }
        $all = self::all();
        unset($all[$projectId]['foto']);
        Storage::disk('local')->put(self::FILE, json_encode($all, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    /** @return array<int, array<string, mixed>> */
    private static function all(): array
    {
        if (! Storage::disk('local')->exists(self::FILE)) {
            return [];
        }
        $data = json_decode((string) Storage::disk('local')->get(self::FILE), true);

        return is_array($data) ? $data : [];
    }

    /** Payload ringkas untuk API/halaman — dipakai juga sisi mahasiswa/mobile. */
    public static function card(PblProject $p): array
    {
        $meta = self::get((int) $p->id);

        return [
            'foto_url' => $meta['foto'],
            'deskripsi' => $meta['deskripsi'],
            'progress' => $meta['progress'],
            'status_label' => $meta['status'] ?? ($p->aktif ? 'Berjalan' : 'Nonaktif'),
            'tahun_ajaran' => $meta['tahun_ajaran'],
            'kategori' => $meta['kategori'],
            'semester' => $meta['semester'],
            'periode_mulai' => $meta['periode_mulai'],
            'periode_selesai' => $meta['periode_selesai'],
        ];
    }
}
