<?php

namespace App\Support\AccountImport;

use ZipArchive;

/**
 * Definisi kolom resmi per jenis akun dan pembuat file template (.csv / .xlsx satu sheet).
 */
class AccountImportTemplate
{
    public const TYPES = ['mahasiswa', 'dosen', 'laboran'];

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
            default => ucfirst($type),
        };
    }

    public static function identifierColumn(string $type): string
    {
        return match ($type) {
            'mahasiswa' => 'nim',
            'dosen' => 'nidn',
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

    /** Alias header yang diterima => nama kolom resmi. */
    public static function aliases(string $type): array
    {
        return [
            'nitk_nidn' => self::identifierColumn($type),
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

    /** Template .xlsx: satu sheet "Import", seluruh kolom berformat Teks agar NIM/NIDN tidak berubah. */
    public static function xlsx(string $type): string
    {
        $headers = array_keys(self::columns($type));
        $count = count($headers);

        $cells = '';
        foreach ($headers as $i => $name) {
            $ref = chr(65 + $i).'1';
            $cells .= '<c r="'.$ref.'" t="inlineStr" s="2"><is><t>'.htmlspecialchars($name, ENT_XML1).'</t></is></c>';
        }

        $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            .'<cols><col min="1" max="'.$count.'" width="22" style="1" customWidth="1"/></cols>'
            .'<sheetData><row r="1">'.$cells.'</row></sheetData>'
            .'</worksheet>';

        $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
            .'<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
            .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="3">'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="49" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            .'<xf numFmtId="49" fontId="1" fillId="0" borderId="0" xfId="0" applyNumberFormat="1" applyFont="1"/>'
            .'</cellXfs>'
            .'</styleSheet>';

        $files = [
            '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
                .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
                .'<Default Extension="xml" ContentType="application/xml"/>'
                .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
                .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
                .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
                .'</Types>',
            '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
                .'</Relationships>',
            'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
                .'<sheets><sheet name="Import" sheetId="1" r:id="rId1"/></sheets>'
                .'</workbook>',
            'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
                .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
                .'</Relationships>',
            'xl/worksheets/sheet1.xml' => $sheet,
            'xl/styles.xml' => $styles,
        ];

        return self::zip($files);
    }

    /** @param  array<string, string>  $files */
    public static function zip(array $files): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'simalas-xlsx-');
        $zip = new ZipArchive;
        if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Gagal membuat file .xlsx.');
        }
        foreach ($files as $name => $content) {
            $zip->addFromString($name, $content);
        }
        $zip->close();
        $binary = (string) file_get_contents($tmp);
        @unlink($tmp);

        return $binary;
    }
}
