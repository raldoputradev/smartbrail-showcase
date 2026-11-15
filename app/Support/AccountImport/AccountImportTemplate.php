<?php

namespace App\Support\AccountImport;

use ZipArchive;

/**
 * Definisi kolom resmi per jenis akun dan pembuat file template (.csv / .xlsx satu sheet).
 */
class AccountImportTemplate
{
    public const TYPES = ['mahasiswa', 'dosen', 'laboran', 'semua', 'petugas'];

    /** Kolom yang sengaja diabaikan: diisi pemilik saat aktivasi atau tidak boleh diatur lewat import. */
    public const IGNORED_SENSITIVE = [
        'email', 'e_mail', 'no_hp', 'hp', 'nomor_hp', 'telepon', 'no_telp', 'whatsapp', 'wa',
        'password', 'kata_sandi', 'sandi', 'finger_id', 'status', 'is_active', 'approval_status',
    ];

    public static function label(string $type): string
    {
        return match ($type) {
            'mahasiswa' => 'Mahasiswa',
            'dosen' => 'Dosen',
            'laboran' => 'Laboran (admin)',
            'semua' => 'Semua Role (campuran)',
            'petugas' => 'Petugas / Cleaning Service',
            default => ucfirst($type),
        };
    }

    public static function identifierColumn(string $type): string
    {
        return match ($type) {
            'mahasiswa' => 'nim',
            'dosen' => 'nidn',
            'semua', 'petugas' => 'id',
            default => 'nitk',
        };
    }

    /**
     * Kolom resmi: nama => [wajib, keterangan].
     *
     * @return array<string, array{0: bool, 1: string}>
     */
    public static function columns(string $type): array
    {
        if ($type === 'semua') {
            return self::columnsSemua();
        }
        if ($type === 'petugas') {
            return self::columnsPetugas();
        }

        $id = self::identifierColumn($type);
        $idLabel = strtoupper($id);
        $base = [
            $id => [true, "{$idLabel} (ID login, unik, maks 50 karakter, ditulis apa adanya)"],
            'nama_lengkap' => [true, 'Nama lengkap (maks 255 karakter)'],
        ];

        if ($type === 'mahasiswa') {
            $base += [
                'kelas' => [true, 'Kelas, mis. Robotika A (maks 80)'],
                'tim' => [true, 'Tim/PBL (maks 80)'],
                'angkatan' => [true, 'Tahun angkatan 2020–'.(now()->year + 1)],
                'semester' => [true, 'Angka 1–8'],
                'semester_type' => [true, 'ganjil atau genap'],
                'shift' => [true, 'pagi atau malam'],
                'program_studi' => [false, 'Kode/nama prodi; kosong = disimpulkan dari kelas'],
            ];
        }

        $base['role'] = [false, "Opsional; jika diisi harus \"{$type}\". Tidak memberi hak akses."];

        return $base;
    }

    /** Kolom untuk import campuran: kolom identitas terpisah, role disimpulkan dari yang terisi. */
    private static function columnsSemua(): array
    {
        return [
            'nim' => [false, 'Salah satu ID wajib: NIM untuk baris mahasiswa'],
            'nidn' => [false, 'Salah satu ID wajib: NIDN untuk baris dosen'],
            'nitk' => [false, 'Salah satu ID wajib: NITK untuk baris laboran'],
            'nama_lengkap' => [true, 'Nama lengkap (maks 255 karakter)'],
            'kelas' => [false, 'Wajib untuk baris mahasiswa'],
            'tim' => [false, 'Wajib untuk baris mahasiswa; nama/kode proyek PBL (format "KODE | Nama" diterima)'],
            'angkatan' => [false, 'Wajib untuk mahasiswa: tahun 2020–'.(now()->year + 1)],
            'semester' => [false, 'Wajib untuk mahasiswa: angka 1–8'],
            'semester_type' => [false, 'Wajib untuk mahasiswa: ganjil atau genap'],
            'shift' => [false, 'Wajib untuk mahasiswa: pagi atau malam'],
            'program_studi' => [false, 'Opsional; kosong = disimpulkan dari kelas'],
            'role' => [false, 'Opsional; disimpulkan dari kolom identitas yang diisi'],
        ];
    }

    /** Petugas/CS: bukan akun portal — record ruangan_petugas untuk akses mesin ruangan. */
    private static function columnsPetugas(): array
    {
        return [
            'id' => [true, 'ID internal petugas / NITK (unik, maks 50 karakter)'],
            'nama_lengkap' => [true, 'Nama lengkap (maks 120 karakter)'],
            'tipe_kredensial' => [false, 'pin, rfid, atau fingerprint; kosong = PIN otomatis'],
            'kredensial' => [false, 'PIN / nomor RFID / referensi finger ID — disimpan sebagai hash; kosong = PIN acak dibuat otomatis, atur ulang di halaman Petugas Ruangan'],
            'berlaku_mulai' => [false, 'Tanggal YYYY-MM-DD (opsional)'],
            'berlaku_selesai' => [false, 'Tanggal YYYY-MM-DD (opsional)'],
        ];
    }

    /** Alias header yang diterima => nama kolom resmi. */
    public static function aliases(string $type): array
    {
        $extra = $type === 'petugas' ? [
            'nitk' => 'id',
            'nitk_nidn' => 'id',
            'id_internal' => 'id',
            'id_pegawai' => 'id',
            'identitas' => 'id',
            'id_login' => 'id',
        ] : [];

        return $extra + [
            'nitk_nidn' => $extra['nitk_nidn'] ?? self::identifierColumn($type),
            'nama' => 'nama_lengkap',
            'prodi' => 'program_studi',
            'jenis_semester' => 'semester_type',
        ];
    }

    public static function normalizeHeader(string $header): string
    {
        $h = strtolower(trim($header));
        $h = (string) preg_replace('/[\s\-\.\/]+/', '_', $h);

        return trim($h, '_');
    }

    public static function csv(string $type): string
    {
        return "\xEF\xBB\xBF".implode(',', array_keys(self::columns($type)))."\r\n";
    }

    /** Nama file unduhan template per jenis — masing-masing file berbeda. */
    public static function filename(string $type, string $format): string
    {
        $names = [
            'mahasiswa' => 'Template_Import_Akun_SmartBrail_Mahasiswa',
            'dosen' => 'Template_Import_Akun_SmartBrail_Dosen',
            'laboran' => 'Template_Import_Akun_SmartBrail_Laboran',
            'petugas' => 'Template_Import_Akun_SmartBrail_Cleaning_Service',
            'semua' => 'Template_Import_Akun_SmartBrail_Semua_Role',
        ];

        return ($names[$type] ?? 'Template_Import_Akun_SmartBrail_'.$type).'.'.$format;
    }

    /** Baris contoh per jenis — selalu diawali "CONTOH" sehingga otomatis dilewati import. */
    public static function exampleRows(string $type): array
    {
        $rows = match ($type) {
            'mahasiswa' => [
                ['nim' => '23010001', 'nama_lengkap' => 'CONTOH - Budi Santoso', 'kelas' => 'RO-6A',
                 'tim' => 'PBL-TRR-001 | KRAI', 'angkatan' => '2023', 'semester' => '7',
                 'semester_type' => 'ganjil', 'shift' => 'pagi', 'program_studi' => 'Teknik Robotika'],
            ],
            'dosen' => [
                ['nidn' => '0012345678', 'nama_lengkap' => 'CONTOH - Siti Rahmawati'],
            ],
            'laboran' => [
                ['nitk' => '1987654321', 'nama_lengkap' => 'CONTOH - Andi Pratama'],
            ],
            'petugas' => [
                ['id' => 'CS001', 'nama_lengkap' => 'CONTOH - Petugas Kebersihan',
                 'tipe_kredensial' => 'pin', 'kredensial' => '123456', 'berlaku_mulai' => '2026-01-01'],
            ],
            'semua' => [
                ['nim' => '23010001', 'nama_lengkap' => 'CONTOH - Budi Santoso', 'kelas' => 'RO-6A',
                 'tim' => 'PBL-TRR-001 | KRAI', 'angkatan' => '2023', 'semester' => '7',
                 'semester_type' => 'ganjil', 'shift' => 'pagi', 'program_studi' => 'Teknik Robotika'],
                ['nidn' => '0012345678', 'nama_lengkap' => 'CONTOH - Siti Rahmawati'],
                ['nitk' => '1987654321', 'nama_lengkap' => 'CONTOH - Andi Pratama'],
            ],
            default => [],
        };

        return array_map(function (array $example) use ($type) {
            $row = [];
            foreach (self::columns($type) as $name => $_) {
                $row[] = $example[$name] ?? '';
            }

            return $row;
        }, $rows);
    }

    /** Panduan penggunaan per jenis untuk sheet PETUNJUK. */
    private static function guideRows(string $type): array
    {
        $label = mb_strtoupper(self::label($type));
        $rows = [
            ["SMARTBRAIL  |  TEMPLATE IMPORT AKUN — {$label}"],
            ['Isi data pada sheet IMPORT_AKUN mulai dari baris 2. Jangan mengubah nama atau urutan header.'],
            [],
            ['CARA PAKAI'],
            ['01 · Satu baris mewakili satu akun/petugas. Baris berawalan "CONTOH" otomatis dilewati sistem.'],
            ['02 · Email, nomor HP, dan password TIDAK diisi di file ini — akun dibuat dengan PASSWORD SEMENTARA.'],
            ['03 · Pemilik akun login dengan password sementara lalu WAJIB mengganti password (mengamankan akun sendiri).'],
            ['04 · Kendala login atau akses ruangan dilaporkan ke laboran / dosen.'],
            ['05 · Simpan sebagai .xlsx atau CSV UTF-8 sebelum diunggah ke SmartBrail.'],
            [],
            ['Sel kolom tertentu punya DROPDOWN (kelas, tim, angkatan, semester, shift, dll.) — pilih dari daftar,'],
            ['atau ketik nilai lain langsung bila belum ada di daftar (dropdown hanya saran, bukan pembatas).'],
        ];

        $extra = match ($type) {
            'semua' => [
                ['06 · Isi TEPAT SATU kolom identitas per baris: nim = mahasiswa, nidn = dosen, nitk = laboran. Role disimpulkan otomatis.'],
                ['07 · Kolom kelas/tim/angkatan/semester/semester_type/shift wajib hanya untuk baris mahasiswa.'],
                ['08 · Kolom tim menerima nama proyek atau format "KODE | Nama" — daftar proyek ada di sheet DAFTAR_PBL.'],
            ],
            'mahasiswa' => [
                ['06 · Kolom tim menerima nama proyek atau format "KODE | Nama" — daftar proyek ada di sheet DAFTAR_PBL.'],
            ],
            'petugas' => [
                ['06 · Petugas/CS TIDAK mendapat akun web — akses ruangan lewat mesin (kredensial).'],
                ['07 · tipe_kredensial: pin, rfid, atau fingerprint. kredensial disimpan sebagai hash di server.'],
                ['08 · Jadwal ruangan petugas diatur setelah import di halaman Petugas Ruangan.'],
            ],
            default => [],
        };
        foreach ($extra as $row) {
            $rows[] = $row;
        }

        $rows[] = [];
        $rows[] = ['PANDUAN KOLOM'];
        $rows[] = ['Kolom', 'Wajib?', 'Keterangan'];
        foreach (self::columns($type) as $name => [$required, $desc]) {
            $rows[] = [$name, $required ? 'Ya' : 'Opsional', $desc];
        }

        return $rows;
    }

    /**
     * Template .xlsx multi-sheet: PETUNJUK + IMPORT_AKUN (+ DAFTAR_PBL untuk jenis dengan kolom tim).
     * Seluruh sel berformat Teks agar NIM/NIDN tidak berubah.
     *
     * @param  list<list<string>>  $projects  baris [kode, nama, manpro, co_manpro, shift] untuk sheet DAFTAR_PBL
     */
    public static function xlsx(string $type, array $projects = []): string
    {
        $headers = array_keys(self::columns($type));
        $dataRows = array_merge([$headers], self::exampleRows($type));

        [$choiceRows, $choices] = self::choiceLists($type);
        $definedNames = [];

        $sheets = [
            ['name' => 'PETUNJUK', 'rows' => self::guideRows($type), 'freeze' => false,
             'widths' => [30, 12, 95]],
            ['name' => 'IMPORT_AKUN', 'rows' => $dataRows, 'freeze' => true,
             'widths' => self::importWidths($type),
             'validations' => self::validations($type, $headers, $choices)],
        ];
        if ($choiceRows !== null) {
            $sheets[] = ['name' => 'PILIHAN', 'rows' => $choiceRows, 'freeze' => true,
                'widths' => [26, 40, 30]];
            foreach ($choices as $name => $def) {
                $colRef = self::colRef($def['col']);
                $lastRow = 1 + count($def['values']);
                $definedNames[$name] = 'PILIHAN!$'.$colRef.'$2:$'.$colRef.'$'.$lastRow;
            }
        }
        if ($projects !== [] && in_array($type, ['mahasiswa', 'semua'], true)) {
            $sheets[] = [
                'name' => 'DAFTAR_PBL',
                'rows' => array_merge([['kode_pbl', 'nama_pbl', 'manpro', 'co_manpro', 'shift']], $projects),
                'freeze' => true,
                'widths' => [16, 46, 24, 24, 12],
            ];
        }

        return self::workbookXml($sheets, $definedNames);
    }

    /** Lebar kolom (Excel units) untuk sheet IMPORT_AKUN per jenis. */
    private static function importWidths(string $type): array
    {
        $widths = [
            'nim' => 16, 'nidn' => 16, 'nitk' => 16, 'id' => 16,
            'nama_lengkap' => 30, 'kelas' => 18, 'tim' => 34,
            'angkatan' => 11, 'semester' => 11, 'semester_type' => 15,
            'shift' => 10, 'program_studi' => 24, 'role' => 12,
            'tipe_kredensial' => 16, 'kredensial' => 24,
            'berlaku_mulai' => 15, 'berlaku_selesai' => 16,
        ];

        return array_map(
            fn (string $col) => $widths[$col] ?? 18,
            array_keys(self::columns($type))
        );
    }

    /**
     * Daftar pilihan panjang (kelas/tim/prodi) — diisi ke sheet PILIHAN
     * lalu dirujuk kolom import lewat named range.
     *
     * @return array{0: ?list<list<string>>, 1: array<string, array{col: int, values: list<string>}>}
     */
    private static function choiceLists(string $type): array
    {
        if (! in_array($type, ['mahasiswa', 'semua'], true)) {
            return [null, []];
        }

        $lists = [
            'LstKelas' => \App\Support\MahasiswaProfileOptions::kelasOptions(),
            'LstTim' => \App\Support\MahasiswaProfileOptions::pblTeamOptions(),
            'LstProdi' => array_map(
                fn (array $p) => trim($p['kode'].' - '.$p['nama'], ' -'),
                \App\Support\MahasiswaProfileOptions::programStudiList()
            ),
        ];

        $headers = ['kelas', 'tim_pbl', 'program_studi'];
        $rows = [$headers];
        $max = max(array_map('count', $lists));
        $choices = [];
        foreach (array_values($lists) as $col => $values) {
            $choices[array_keys($lists)[$col]] = ['col' => $col, 'values' => $values];
        }
        for ($i = 0; $i < $max; $i++) {
            $rows[] = array_map(fn (array $values) => $values[$i] ?? '', array_values($lists));
        }

        return [$rows, $choices];
    }

    /**
     * Dropdown validasi per kolom (saran — nilai lain tetap boleh diketik).
     *
     * @param  list<string>  $headers
     * @param  array<string, array{col: int, values: list<string>}>  $choices
     * @return list<array{sqref: string, formula: string}>
     */
    private static function validations(string $type, array $headers, array $choices): array
    {
        $angkatan = implode(',', \App\Support\MahasiswaProfileOptions::angkatanYears());
        $inline = [
            'angkatan' => '"'.$angkatan.'"',
            'semester' => '"1,2,3,4,5,6,7,8"',
            'semester_type' => '"ganjil,genap"',
            'shift' => '"pagi,malam"',
            'tipe_kredensial' => '"pin,rfid,fingerprint"',
            'role' => $type === 'semua' ? '"mahasiswa,dosen,laboran"' : '"'.$type.'"',
        ];
        $named = [
            'kelas' => 'LstKelas',
            'tim' => 'LstTim',
            'program_studi' => 'LstProdi',
        ];

        $out = [];
        foreach ($headers as $i => $h) {
            $formula = null;
            if (isset($named[$h], $choices[$named[$h]])) {
                $formula = $named[$h];
            } elseif (isset($inline[$h])) {
                $formula = $inline[$h];
            }
            if ($formula === null) {
                continue;
            }
            $col = self::colRef($i);
            $out[] = ['sqref' => $col.'2:'.$col.'2000', 'formula' => $formula];
        }

        return $out;
    }

    /** Bangun workbook .xlsx minimalis multi-sheet dengan inline strings. */
    private static function workbookXml(array $sheets, array $definedNames = []): string
    {
        $sheetXml = static function (array $sheet): string {
            $out = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
            if ($sheet['freeze']) {
                $out .= '<sheetViews><sheetView workbookViewId="0">'
                    .'<pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/>'
                    .'<selection pane="bottomLeft"/>'
                    .'</sheetView></sheetViews>';
            } else {
                $out .= '<sheetViews><sheetView workbookViewId="0"/></sheetViews>';
            }
            if (! empty($sheet['widths'])) {
                $out .= '<cols>';
                foreach ($sheet['widths'] as $i => $w) {
                    $n = $i + 1;
                    $out .= '<col min="'.$n.'" max="'.$n.'" width="'.$w.'" customWidth="1"/>';
                }
                $out .= '</cols>';
            }
            $out .= '<sheetData>';
            foreach ($sheet['rows'] as $r => $row) {
                $out .= '<row r="'.($r + 1).'">';
                foreach ($row as $c => $v) {
                    $ref = self::colRef($c).($r + 1);
                    $out .= '<c r="'.$ref.'" t="inlineStr" s="2"><is><t>'.htmlspecialchars((string) $v, ENT_XML1).'</t></is></c>';
                }
                $out .= '</row>';
            }
            $out .= '</sheetData>';

            if (! empty($sheet['validations'])) {
                $out .= '<dataValidations count="'.count($sheet['validations']).'">';
                foreach ($sheet['validations'] as $dv) {
                    $out .= '<dataValidation type="list" allowBlank="1" showErrorMessage="0" sqref="'.$dv['sqref'].'">'
                        .'<formula1>'.htmlspecialchars($dv['formula'], ENT_XML1).'</formula1>'
                        .'</dataValidation>';
                }
                $out .= '</dataValidations>';
            }

            return $out.'</worksheet>';
        };

        $sheetTags = '';
        $relTags = '';
        $typeTags = '';
        $files = [];
        foreach ($sheets as $i => $sheet) {
            $n = $i + 1;
            $name = htmlspecialchars($sheet['name'], ENT_XML1);
            $sheetTags .= '<sheet name="'.$name.'" sheetId="'.$n.'" r:id="rId'.$n.'"/>';
            $relTags .= '<Relationship Id="rId'.$n.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$n.'.xml"/>';
            $typeTags .= '<Override PartName="/xl/worksheets/sheet'.$n.'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
            $files['xl/worksheets/sheet'.$n.'.xml'] = $sheetXml($sheet);
        }
        $relTags .= '<Relationship Id="rId'.(count($sheets) + 1).'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';

        $defTags = '';
        if ($definedNames !== []) {
            $defTags = '<definedNames>';
            foreach ($definedNames as $name => $ref) {
                $defTags .= '<definedName name="'.$name.'">'.htmlspecialchars($ref, ENT_XML1).'</definedName>';
            }
            $defTags .= '</definedNames>';
        }

        $files['xl/workbook.xml'] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets>'.$sheetTags.'</sheets>'.$defTags.'</workbook>';

        $files['xl/_rels/workbook.xml.rels'] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$relTags.'</Relationships>';

        $files['xl/styles.xml'] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
            .'<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
            .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="3">'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="49" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            .'<xf numFmtId="49" fontId="1" fillId="0" borderId="0" xfId="0" applyNumberFormat="1" applyFont="1"/>'
            .'</cellXfs></styleSheet>';

        $files['[Content_Types].xml'] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .$typeTags.'</Types>';

        $files['_rels/.rels'] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>';

        return self::zipPack($files);
    }

    private static function colRef(int $index): string
    {
        $ref = '';
        $index++;
        while ($index > 0) {
            $ref = chr(65 + (($index - 1) % 26)).$ref;
            $index = intdiv($index - 1, 26);
        }

        return $ref;
    }

    /** Kemas daftar file menjadi arsip .xlsx (zip) dalam memori. */
    private static function zipPack(array $files): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'imptpl');
        $zip = new \ZipArchive;
        if ($zip->open($tmp, \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Gagal membuat arsip template.');
        }
        foreach ($files as $name => $content) {
            $zip->addFromString($name, $content);
        }
        $zip->close();
        $bin = (string) file_get_contents($tmp);
        @unlink($tmp);

        return $bin;
    }
}
