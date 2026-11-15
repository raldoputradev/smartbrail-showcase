<?php

namespace App\Support\AccountImport;

use XMLReader;
use ZipArchive;

/**
 * Pembaca tabel sederhana untuk import akun: CSV (UTF-8) dan .xlsx satu sheet.
 *
 * Hasil: ['headers' => list<string>, 'rows' => list<array{line: int, cells: list<array{v: string, issue: ?string}>}>]
 * Sel tidak pernah dihitung ulang (formula ditandai, bukan dievaluasi) dan identitas tidak diubah.
 */
class TabularFileReader
{
    public const MAX_UPLOAD_BYTES = 2 * 1024 * 1024;

    public const MAX_DATA_ROWS = 1000;

    public const MAX_COLUMNS = 30;

    public const MAX_CELL_CHARS = 500;

    private const MAX_ZIP_ENTRIES = 64;

    private const MAX_ENTRY_BYTES = 8 * 1024 * 1024;

    private const MAX_TOTAL_UNCOMPRESSED = 16 * 1024 * 1024;

    private const NS_MAIN = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

    /**
     * @return array{headers: list<string>, rows: list<array{line: int, cells: list<array{v: string, issue: ?string}>}>}
     */
    public function read(string $path, string $originalName, array $expected = []): array
    {
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!is_file($path)) {
            throw new ImportFileException('File tidak ditemukan atau gagal diunggah.');
        }
        $size = filesize($path);
        if ($size === false || $size === 0) {
            throw new ImportFileException('File kosong.');
        }
        if ($size > self::MAX_UPLOAD_BYTES) {
            throw new ImportFileException('Ukuran file melebihi batas 2 MB.');
        }

        $table = match ($ext) {
            'csv', 'txt' => $this->readCsv($path),
            'xlsx' => $this->readXlsx($path, $expected),
            'xls' => throw new ImportFileException('Format .xls (Excel 97-2003) tidak didukung. Simpan ulang sebagai .xlsx atau CSV UTF-8.'),
            'xlsm', 'xltm' => throw new ImportFileException('File Excel bermakro (.xlsm) ditolak. Gunakan template .xlsx tanpa makro.'),
            default => throw new ImportFileException('Format file tidak didukung. Gunakan template .xlsx atau .csv.'),
        };

        return $this->splitHeader($table, $expected);
    }

    /**
     * @param  list<array{line: int, cells: list<array{v: string, issue: ?string}>}>  $rows
     */
    private function headerScore(array $cells, array $expected): int
    {
        $score = 0;
        foreach ($cells as $cell) {
            $name = AccountImportTemplate::normalizeHeader($cell['v']);
            if ($name !== '' && isset($expected[$name])) {
                $score++;
            }
        }

        return $score;
    }

    private function splitHeader(array $rows, array $expected = []): array
    {
        $headerIndex = null;
        // Baris header = baris dengan >= 2 sel cocok nama kolom yang dikenal
        // (menoleransi baris judul/keterangan di atas header, mis. template multi-baris).
        if ($expected !== []) {
            $best = 0;
            foreach ($rows as $i => $row) {
                $score = $this->headerScore($row['cells'], $expected);
                if ($score >= 2 && $score > $best) {
                    $best = $score;
                    $headerIndex = $i;
                }
            }
        }
        if ($headerIndex === null) {
            foreach ($rows as $i => $row) {
                if ($this->rowHasContent($row['cells'])) {
                    $headerIndex = $i;
                    break;
                }
            }
        }
        if ($headerIndex === null) {
            throw new ImportFileException('File tidak berisi header kolom.');
        }

        $headers = [];
        foreach ($rows[$headerIndex]['cells'] as $cell) {
            if ($cell['issue'] !== null) {
                throw new ImportFileException('Header kolom tidak boleh berisi formula atau sel non-teks.');
            }
            $headers[] = $cell['v'];
        }

        $data = array_values(array_slice($rows, $headerIndex + 1));
        // Buang baris kosong di ekor (sisa format Excel di luar area isian).
        while ($data !== [] && ! $this->rowHasContent(end($data)['cells'])) {
            array_pop($data);
        }
        if (count($data) > self::MAX_DATA_ROWS) {
            throw new ImportFileException('Jumlah baris melebihi batas '.self::MAX_DATA_ROWS.' per file. Pecah menjadi beberapa file.');
        }

        return ['headers' => $headers, 'rows' => $data];
    }

    private function rowHasContent(array $cells): bool
    {
        foreach ($cells as $cell) {
            if (trim($cell['v']) !== '' || $cell['issue'] !== null) {
                return true;
            }
        }

        return false;
    }

    // ---------------------------------------------------------------- CSV

    private function readCsv(string $path): array
    {
        $content = (string) file_get_contents($path);
        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            $content = substr($content, 3);
        }
        if (str_starts_with($content, "\xFF\xFE") || str_starts_with($content, "\xFE\xFF")) {
            throw new ImportFileException('CSV UTF-16 tidak didukung. Simpan sebagai "CSV UTF-8".');
        }
        if (!mb_check_encoding($content, 'UTF-8')) {
            throw new ImportFileException('CSV harus berencoding UTF-8 (pilih "CSV UTF-8" saat menyimpan dari Excel).');
        }
        if (str_contains($content, "\0")) {
            throw new ImportFileException('File CSV rusak atau bukan teks.');
        }

        $firstLine = strtok($content, "\r\n") ?: '';
        $delimiter = $this->detectDelimiter($firstLine);

        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $content);
        rewind($handle);

        $rows = [];
        $line = 0;
        while (($fields = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
            $line++;
            if ($fields === [null]) {
                $rows[] = ['line' => $line, 'cells' => []];
                continue;
            }
            if (count($fields) > self::MAX_COLUMNS) {
                fclose($handle);
                throw new ImportFileException("Baris {$line} memiliki lebih dari ".self::MAX_COLUMNS.' kolom.');
            }
            $cells = [];
            foreach ($fields as $value) {
                $cells[] = $this->csvCell((string) $value);
            }
            $rows[] = ['line' => $line, 'cells' => $cells];
            if (count($rows) > self::MAX_DATA_ROWS + 50) {
                fclose($handle);
                throw new ImportFileException('Jumlah baris melebihi batas '.self::MAX_DATA_ROWS.' per file.');
            }
        }
        fclose($handle);

        return $rows;
    }

    private function detectDelimiter(string $line): string
    {
        $best = ',';
        $bestCount = 0;
        foreach ([',', ';', "\t"] as $candidate) {
            $count = substr_count($line, $candidate);
            if ($count > $bestCount) {
                $best = $candidate;
                $bestCount = $count;
            }
        }

        return $best;
    }

    /** @return array{v: string, issue: ?string} */
    private function csvCell(string $value): array
    {
        if (mb_strlen($value) > self::MAX_CELL_CHARS) {
            return ['v' => '', 'issue' => 'isi sel terlalu panjang'];
        }
        $trimmed = ltrim($value);
        if ($trimmed !== '' && in_array($trimmed[0], ['=', '+', '-', '@'], true)) {
            return ['v' => $value, 'issue' => 'diawali karakter formula (= + - @); formula tidak diproses'];
        }

        return ['v' => $value, 'issue' => null];
    }

    // ---------------------------------------------------------------- XLSX

    private function readXlsx(string $path, array $expected = []): array
    {
        $head = (string) file_get_contents($path, false, null, 0, 4);
        if ($head !== "PK\x03\x04") {
            throw new ImportFileException('File .xlsx rusak atau bukan file Excel modern.');
        }

        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            throw new ImportFileException('File .xlsx rusak dan tidak bisa dibuka.');
        }

        try {
            $this->assertZipLimits($zip);

            $contentTypes = $this->zipXml($zip, '[Content_Types].xml');
            if (stripos($contentTypes, 'macroEnabled') !== false || $zip->locateName('xl/vbaProject.bin') !== false) {
                throw new ImportFileException('File Excel bermakro ditolak. Gunakan template .xlsx tanpa makro.');
            }
            if (!str_contains($contentTypes, 'spreadsheetml.sheet.main+xml')) {
                throw new ImportFileException('Struktur .xlsx tidak dikenali (bukan workbook Excel standar).');
            }

            $workbookPath = $this->officeDocumentPath($zip);
            $workbookRels = $this->relationships($zip, $this->relsPathFor($workbookPath));
            $sheetPaths = $this->sheetPaths($zip, $workbookPath, $workbookRels);

            $sharedStrings = [];
            foreach ($workbookRels as $rel) {
                if (str_ends_with($rel['type'], '/sharedStrings')) {
                    $sharedStrings = $this->readSharedStrings($this->zipXml($zip, $this->resolveTarget($workbookPath, $rel['target'])));
                }
            }

            if (count($sheetPaths) === 1) {
                return $this->readSheet($this->zipXml($zip, $sheetPaths[0]), $sharedStrings);
            }

            // Multi-sheet (mis. PETUNJUK + IMPORT + DAFTAR): pilih sheet berisi header yang dikenal.
            $best = null;
            $bestScore = 0;
            foreach ($sheetPaths as $sheetPath) {
                $table = $this->readSheet($this->zipXml($zip, $sheetPath), $sharedStrings);
                foreach ($table as $row) {
                    $score = $this->headerScore($row['cells'], $expected);
                    if ($score >= 2 && $score > $bestScore) {
                        $bestScore = $score;
                        $best = $table;
                    }
                }
            }
            if ($best === null) {
                throw new ImportFileException('File memiliki beberapa sheet tetapi tidak ada yang berisi header kolom import. Isi data pada sheet yang memuat kolom template.');
            }

            return $best;
        } finally {
            $zip->close();
        }
    }

    private function assertZipLimits(ZipArchive $zip): void
    {
        if ($zip->numFiles < 1 || $zip->numFiles > self::MAX_ZIP_ENTRIES) {
            throw new ImportFileException('Struktur .xlsx tidak wajar (jumlah entri ZIP di luar batas).');
        }
        $total = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            if ($stat === false) {
                throw new ImportFileException('File .xlsx rusak (entri ZIP tidak terbaca).');
            }
            $name = (string) $stat['name'];
            if (str_contains($name, '..') || str_starts_with($name, '/') || str_contains($name, '\\')) {
                throw new ImportFileException('File .xlsx berisi path entri yang tidak aman.');
            }
            if ($stat['size'] > self::MAX_ENTRY_BYTES) {
                throw new ImportFileException('Isi .xlsx terlalu besar setelah dekompresi.');
            }
            $total += $stat['size'];
            if ($total > self::MAX_TOTAL_UNCOMPRESSED) {
                throw new ImportFileException('Isi .xlsx terlalu besar setelah dekompresi.');
            }
        }
    }

    private function zipXml(ZipArchive $zip, string $name): string
    {
        $stat = $zip->statName($name);
        if ($stat === false) {
            throw new ImportFileException("Struktur .xlsx tidak lengkap ({$name} tidak ada).");
        }
        $xml = $zip->getFromName($name, self::MAX_ENTRY_BYTES);
        if ($xml === false || strlen($xml) !== (int) $stat['size']) {
            throw new ImportFileException('File .xlsx rusak (entri tidak bisa dibaca utuh).');
        }
        if (preg_match('/<!DOCTYPE|<!ENTITY/i', $xml)) {
            throw new ImportFileException('File .xlsx berisi deklarasi DOCTYPE/ENTITY dan ditolak.');
        }

        return $xml;
    }

    private function xmlReader(string $xml): XMLReader
    {
        $reader = XMLReader::XML($xml, 'UTF-8', LIBXML_NONET | LIBXML_COMPACT);
        if (!$reader instanceof XMLReader) {
            throw new ImportFileException('XML di dalam .xlsx tidak valid.');
        }

        return $reader;
    }

    /** @return list<array{id: string, type: string, target: string}> */
    private function relationships(ZipArchive $zip, string $relsPath): array
    {
        $reader = $this->xmlReader($this->zipXml($zip, $relsPath));
        $rels = [];
        $prev = libxml_use_internal_errors(true);
        try {
            while ($reader->read()) {
                if ($reader->nodeType === XMLReader::ELEMENT && $reader->localName === 'Relationship') {
                    if (strcasecmp((string) $reader->getAttribute('TargetMode'), 'External') === 0) {
                        continue;
                    }
                    $rels[] = [
                        'id' => (string) $reader->getAttribute('Id'),
                        'type' => (string) $reader->getAttribute('Type'),
                        'target' => (string) $reader->getAttribute('Target'),
                    ];
                }
            }
            $this->assertNoXmlErrors();
        } finally {
            $reader->close();
            libxml_use_internal_errors($prev);
        }

        return $rels;
    }

    private function officeDocumentPath(ZipArchive $zip): string
    {
        foreach ($this->relationships($zip, '_rels/.rels') as $rel) {
            if (str_ends_with($rel['type'], '/officeDocument')) {
                return ltrim($rel['target'], '/');
            }
        }
        throw new ImportFileException('Struktur .xlsx tidak dikenali (workbook tidak ditemukan).');
    }

    private function relsPathFor(string $partPath): string
    {
        $dir = trim(dirname($partPath), '.');

        return ($dir === '' ? '' : $dir.'/').'_rels/'.basename($partPath).'.rels';
    }

    private function resolveTarget(string $basePart, string $target): string
    {
        if (str_starts_with($target, '/')) {
            return ltrim($target, '/');
        }
        $dir = trim(dirname($basePart), '.');
        $parts = $dir === '' ? [] : explode('/', $dir);
        foreach (explode('/', $target) as $segment) {
            if ($segment === '..') {
                array_pop($parts);
            } elseif ($segment !== '.' && $segment !== '') {
                $parts[] = $segment;
            }
        }

        return implode('/', $parts);
    }

    /** @param  list<array{id: string, type: string, target: string}>  $rels */
    /** @return list<string> Semua path worksheet di workbook, urut seperti di Excel. */
    private function sheetPaths(ZipArchive $zip, string $workbookPath, array $rels): array
    {
        $reader = $this->xmlReader($this->zipXml($zip, $workbookPath));
        $sheetIds = [];
        $prev = libxml_use_internal_errors(true);
        try {
            while ($reader->read()) {
                if ($reader->nodeType === XMLReader::ELEMENT && $reader->localName === 'sheet') {
                    $sheetIds[] = (string) $reader->getAttributeNs('id', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');
                }
            }
            $this->assertNoXmlErrors();
        } finally {
            $reader->close();
            libxml_use_internal_errors($prev);
        }

        if ($sheetIds === []) {
            throw new ImportFileException('File .xlsx tidak berisi sheet. Gunakan template resmi.');
        }

        $paths = [];
        foreach ($sheetIds as $id) {
            foreach ($rels as $rel) {
                if ($rel['id'] === $id && str_ends_with($rel['type'], '/worksheet')) {
                    $paths[] = $this->resolveTarget($workbookPath, $rel['target']);
                }
            }
        }
        if ($paths === []) {
            throw new ImportFileException('Sheet di dalam .xlsx bukan worksheet biasa (chart/dialog sheet tidak didukung).');
        }

        return $paths;
    }

    /** @return list<string> */
    private function readSharedStrings(string $xml): array
    {
        $reader = $this->xmlReader($xml);
        $strings = [];
        $current = null;
        $depthPhonetic = 0;
        $prev = libxml_use_internal_errors(true);
        try {
            while ($reader->read()) {
                if ($reader->namespaceURI !== self::NS_MAIN && $reader->nodeType === XMLReader::ELEMENT) {
                    continue;
                }
                if ($reader->nodeType === XMLReader::ELEMENT) {
                    if ($reader->localName === 'si') {
                        $current = '';
                        if ($reader->isEmptyElement) {
                            $strings[] = '';
                            $current = null;
                        }
                    } elseif ($reader->localName === 'rPh' && !$reader->isEmptyElement) {
                        $depthPhonetic++;
                    } elseif ($reader->localName === 't' && $current !== null && $depthPhonetic === 0) {
                        $current .= $reader->readString();
                    }
                } elseif ($reader->nodeType === XMLReader::END_ELEMENT) {
                    if ($reader->localName === 'rPh') {
                        $depthPhonetic = max(0, $depthPhonetic - 1);
                    } elseif ($reader->localName === 'si' && $current !== null) {
                        $strings[] = $current;
                        $current = null;
                    }
                }
                if (count($strings) > 100_000) {
                    throw new ImportFileException('Shared strings .xlsx melebihi batas.');
                }
            }
            $this->assertNoXmlErrors();
        } finally {
            $reader->close();
            libxml_use_internal_errors($prev);
        }

        return $strings;
    }

    /**
     * @param  list<string>  $sharedStrings
     * @return list<array{line: int, cells: list<array{v: string, issue: ?string}>}>
     */
    private function readSheet(string $xml, array $sharedStrings): array
    {
        $reader = $this->xmlReader($xml);
        $rows = [];
        $prev = libxml_use_internal_errors(true);
        $rowNumber = 0;
        $cells = null;
        $nextCol = 0;
        $cell = null;

        try {
            while ($reader->read()) {
                $type = $reader->nodeType;
                $name = $reader->localName;

                if ($type === XMLReader::ELEMENT && $name === 'row') {
                    $r = (int) $reader->getAttribute('r');
                    $rowNumber = $r > 0 ? $r : $rowNumber + 1;
                    $cells = [];
                    $nextCol = 0;
                    if ($reader->isEmptyElement) {
                        $rows[] = ['line' => $rowNumber, 'cells' => []];
                        $cells = null;
                    }
                    if ($rowNumber > self::MAX_DATA_ROWS + 50) {
                        throw new ImportFileException('Jumlah baris melebihi batas '.self::MAX_DATA_ROWS.' per file.');
                    }
                } elseif ($type === XMLReader::END_ELEMENT && $name === 'row' && $cells !== null) {
                    $rows[] = ['line' => $rowNumber, 'cells' => $this->denseCells($cells)];
                    $cells = null;
                } elseif ($type === XMLReader::ELEMENT && $name === 'c' && $cells !== null) {
                    $ref = (string) $reader->getAttribute('r');
                    $col = $ref !== '' ? $this->columnIndex($ref) : $nextCol;
                    if ($col >= self::MAX_COLUMNS) {
                        throw new ImportFileException('File memiliki lebih dari '.self::MAX_COLUMNS.' kolom terisi.');
                    }
                    $nextCol = $col + 1;
                    $cell = ['col' => $col, 't' => (string) $reader->getAttribute('t'), 'v' => null, 'is' => null, 'f' => false];
                    if ($reader->isEmptyElement) {
                        $cells[$col] = $this->finishCell($cell, $sharedStrings);
                        $cell = null;
                    }
                } elseif ($type === XMLReader::END_ELEMENT && $name === 'c' && $cell !== null) {
                    $cells[$cell['col']] = $this->finishCell($cell, $sharedStrings);
                    $cell = null;
                } elseif ($type === XMLReader::ELEMENT && $cell !== null) {
                    if ($name === 'f') {
                        $cell['f'] = true;
                    } elseif ($name === 'v') {
                        $cell['v'] = $reader->readString();
                    } elseif ($name === 't') {
                        $cell['is'] = ($cell['is'] ?? '').$reader->readString();
                    }
                }
            }
            $this->assertNoXmlErrors();
        } finally {
            $reader->close();
            libxml_use_internal_errors($prev);
        }

        return $rows;
    }

    /**
     * @param  array<int, array{v: string, issue: ?string}>  $cells
     * @return list<array{v: string, issue: ?string}>
     */
    private function denseCells(array $cells): array
    {
        if ($cells === []) {
            return [];
        }
        $out = [];
        $max = max(array_keys($cells));
        for ($i = 0; $i <= $max; $i++) {
            $out[] = $cells[$i] ?? ['v' => '', 'issue' => null];
        }

        return $out;
    }

    /**
     * @param  array{col: int, t: string, v: ?string, is: ?string, f: bool}  $cell
     * @param  list<string>  $sharedStrings
     * @return array{v: string, issue: ?string}
     */
    private function finishCell(array $cell, array $sharedStrings): array
    {
        if ($cell['f']) {
            return ['v' => '', 'issue' => 'berisi formula; formula tidak dihitung, ketik nilainya langsung'];
        }

        $value = match ($cell['t']) {
            's' => $sharedStrings[(int) $cell['v']] ?? null,
            'inlineStr' => $cell['is'] ?? '',
            'str' => $cell['v'] ?? '',
            'b' => false,
            'e' => false,
            '', 'n' => $cell['v'] === null ? '' : $this->plainNumber($cell['v']),
            default => false,
        };

        if ($value === null) {
            return ['v' => '', 'issue' => 'referensi teks tidak valid di file .xlsx'];
        }
        if ($value === false) {
            return ['v' => '', 'issue' => 'tipe sel tidak didukung (boolean/error/tanggal); isi sebagai teks'];
        }
        if (is_array($value)) {
            return $value;
        }
        if (mb_strlen($value) > self::MAX_CELL_CHARS) {
            return ['v' => '', 'issue' => 'isi sel terlalu panjang'];
        }

        return ['v' => $value, 'issue' => null];
    }

    /** Angka hanya diterima sebagai bilangan bulat polos; selain itu ditandai agar identitas tidak berubah. */
    private function plainNumber(string $raw): string|array
    {
        if (preg_match('/^\d+$/', $raw)) {
            return $raw;
        }

        return ['v' => '', 'issue' => "angka {$raw} berformat desimal/ilmiah; format kolom sebagai Teks lalu ketik ulang"];
    }

    private function columnIndex(string $ref): int
    {
        if (!preg_match('/^([A-Z]{1,3})\d*$/', strtoupper($ref), $m)) {
            throw new ImportFileException('Referensi sel .xlsx tidak valid.');
        }
        $index = 0;
        foreach (str_split($m[1]) as $ch) {
            $index = $index * 26 + (ord($ch) - 64);
        }

        return $index - 1;
    }

    private function assertNoXmlErrors(): void
    {
        $errors = libxml_get_errors();
        libxml_clear_errors();
        if ($errors !== []) {
            throw new ImportFileException('XML di dalam .xlsx rusak atau tidak valid.');
        }
    }
}
