<?php

namespace App\Support;

use App\Models\PblProject;
use App\Models\User;
use Illuminate\Http\UploadedFile;

class PblCsvImporter
{
    /**
     * @return array{imported: int, updated: int, errors: list<string>}
     */
    public function importProjects(UploadedFile $file): array
    {
        $rows = $this->parseCsv($file);
        $imported = 0;
        $updated = 0;
        $errors = [];

        foreach ($rows as $i => $row) {
            $line = $i + 2;
            $kode = trim((string) ($row['kode'] ?? $row['kode_el'] ?? ''));
            $nama = trim((string) ($row['nama'] ?? $row['nama_pbl'] ?? ''));
            if ($kode === '' && $nama === '') {
                continue;
            }
            if ($kode === '' || $nama === '') {
                $errors[] = "Baris {$line}: kode dan nama wajib diisi.";

                continue;
            }

            $shiftRaw = strtoupper(trim((string) ($row['shift_sesi'] ?? $row['kelas'] ?? $row['sesi'] ?? 'PAGI')));
            $shift = str_contains($shiftRaw, 'MALAM') ? 'malam' : 'pagi';
            $manpro = trim((string) ($row['manpro'] ?? '')) ?: null;
            $coManpro = trim((string) ($row['co_manpro'] ?? $row['co_manpro_nama'] ?? '')) ?: null;

            $manproUserId = $this->findDosenByName($manpro);
            $coManproUserId = $this->findDosenByName($coManpro);

            $existing = PblProject::query()->where('kode', $kode)->first();
            PblProject::query()->updateOrCreate(
                ['kode' => $kode],
                [
                    'nama' => $nama,
                    'manpro' => $manpro,
                    'co_manpro' => $coManpro,
                    'shift_sesi' => $shift,
                    'manpro_user_id' => $manproUserId,
                    'co_manpro_user_id' => $coManproUserId,
                    'aktif' => true,
                ]
            );

            $existing ? $updated++ : $imported++;
        }

        return compact('imported', 'updated', 'errors');
    }

    /**
     * CSV: nim,tim,shift — sinkronkan field users.tim & users.shift
     *
     * @return array{matched: int, skipped: int, errors: list<string>, changes: list<array{user: User, prev_tim: ?string, prev_shift: ?string, new_tim: ?string, new_shift: ?string}>}
     */
    public function syncMahasiswaTim(UploadedFile $file): array
    {
        $rows = $this->parseCsv($file);
        $matched = 0;
        $skipped = 0;
        $errors = [];
        $changes = [];

        foreach ($rows as $i => $row) {
            $line = $i + 2;
            $nim = trim((string) ($row['nim'] ?? $row['nitk_nidn'] ?? ''));
            $tim = trim((string) ($row['tim'] ?? $row['nama_pbl'] ?? $row['tim_pbl'] ?? ''));
            if ($nim === '') {
                continue;
            }

            $user = User::query()->where('role', 'mahasiswa')->where('nitk_nidn', $nim)->first();
            if (!$user) {
                $errors[] = "Baris {$line}: NIM {$nim} tidak ditemukan.";
                $skipped++;

                continue;
            }

            $prevTim = $user->tim;
            $prevShift = $user->shift;

            $shiftRaw = strtolower(trim((string) ($row['shift'] ?? '')));
            $shift = in_array($shiftRaw, ['pagi', 'malam'], true) ? $shiftRaw : null;
            if ($shift === null && $tim !== '') {
                $project = PblProject::query()
                    ->where('nama', $tim)
                    ->orWhere('kode', $tim)
                    ->first();
                $shift = $project?->shift_sesi;
            }

            $user->tim = $tim !== '' ? $tim : $user->tim;
            if ($shift) {
                $user->shift = $shift;
            }
            $user->save();

            if ((string) ($prevTim ?? '') !== (string) ($user->tim ?? '')
                || (string) ($prevShift ?? '') !== (string) ($user->shift ?? '')) {
                $changes[] = [
                    'user' => $user->fresh(),
                    'prev_tim' => $prevTim,
                    'prev_shift' => $prevShift,
                    'new_tim' => $user->tim,
                    'new_shift' => $user->shift,
                ];
            }

            $matched++;
        }

        return compact('matched', 'skipped', 'errors', 'changes');
    }

    /**
     * @return list<array<string, string>>
     */
    private function parseCsv(UploadedFile $file): array
    {
        $content = file_get_contents($file->getRealPath());
        if ($content === false) {
            return [];
        }

        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content) ?? $content;
        $delimiter = str_contains($content, ';') ? ';' : ',';
        $lines = preg_split('/\r\n|\r|\n/', trim($content)) ?: [];
        if (count($lines) < 2) {
            return [];
        }

        $headers = array_map(fn ($h) => strtolower(trim(str_replace(' ', '_', $h))), str_getcsv($lines[0], $delimiter));
        $rows = [];
        for ($i = 1; $i < count($lines); $i++) {
            if (trim($lines[$i]) === '') {
                continue;
            }
            $cols = str_getcsv($lines[$i], $delimiter);
            $row = [];
            foreach ($headers as $idx => $key) {
                $row[$key] = $cols[$idx] ?? '';
            }
            $rows[] = $row;
        }

        return $rows;
    }

    private function findDosenByName(?string $name): ?int
    {
        if ($name === null || trim($name) === '') {
            return null;
        }

        $name = trim($name);
        $user = User::query()
            ->where('role', 'dosen')
            ->where('nama_lengkap', $name)
            ->first();

        return $user?->id;
    }
}
