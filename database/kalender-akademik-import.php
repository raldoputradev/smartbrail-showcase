<?php

// Impor Kalender Akademik Polibatam TA 2026/2027 -> absensi_kalender_libur
// Idempotent: lewati baris yang sudah ada (tanggal + tanggal_selesai + keterangan sama).
// Jalankan: php artisan tinker --execute='include "database/kalender-akademik-import.php";'

use App\Models\AbsensiKalenderLibur;

// [mulai, selesai(optional), jenis, keterangan]
$events = [
    // ── SEMESTER GANJIL ──────────────────────────────────────────
    ['2026-07-20', '2026-08-21', 'informasi', 'Daftar Ulang Semester Ganjil 2026'],
    ['2026-08-01', '2026-08-30', 'libur', 'Libur Semester Genap 2025'],
    ['2026-08-10', null, 'informasi', 'Publikasi Nilai'],
    ['2026-08-11', '2026-08-12', 'informasi', 'Masa Sanggah'],
    ['2026-08-14', null, 'informasi', 'Kunci Nilai'],
    ['2026-08-15', '2026-08-19', 'informasi', 'Evaluasi Mahasiswa KIP Kuliah'],
    ['2026-08-17', null, 'libur', 'HUT Republik Indonesia'],
    ['2026-08-18', '2026-08-21', 'informasi', 'Kegiatan PKKMB'],
    ['2026-08-25', null, 'libur', 'Maulid Nabi Muhammad SAW'],
    ['2026-08-31', '2026-09-04', 'informasi', 'Persiapan Kuliah Perdana'],
    ['2026-09-04', null, 'informasi', 'Batas Akhir Yudisium Semester Genap 2025'],
    ['2026-09-05', null, 'informasi', 'Pelaporan Nilai PDDIKTI'],
    ['2026-09-07', null, 'informasi', 'Kuliah Perdana Semester Ganjil'],
    ['2026-09-14', null, 'informasi', 'Penerimaan RPL & PSPPI Semester Genap 2026'],
    ['2026-10-26', '2026-10-30', 'informasi', 'Minggu Pengganti & Perwalian Pra ATS'],
    ['2026-11-02', '2026-11-13', 'ujian', 'Pelaksanaan ATS Semester Ganjil'],
    ['2026-11-03', '2026-11-05', 'informasi', 'Pelaksanaan Wisuda Tahun 2026'],
    ['2026-11-23', null, 'informasi', 'Minggu ke-9–14: Identifikasi Projek PBL'],
    ['2026-12-24', '2026-12-25', 'libur', 'Hari Natal'],
    ['2027-01-01', null, 'libur', 'Tahun Baru Masehi 2027'],
    ['2027-01-04', '2027-01-08', 'informasi', 'Minggu Pengganti, Perwalian & Daftar Ulang'],
    ['2027-01-05', null, 'libur', "Isra Mi'raj"],
    ['2027-01-11', '2027-01-22', 'ujian', 'Pelaksanaan AAS Semester Ganjil'],
    ['2027-01-18', '2027-02-19', 'informasi', 'Daftar Ulang Semester Genap 2026'],
    ['2027-02-01', '2027-04-01', 'informasi', 'Pendaftaran & Seleksi Jalur Nasional SNPMB'],
    ['2027-02-08', null, 'informasi', 'Publikasi Nilai'],
    ['2027-02-09', '2027-02-10', 'informasi', 'Masa Sanggah'],
    ['2027-02-12', null, 'informasi', 'Kunci Nilai'],
    ['2027-02-13', '2027-02-28', 'libur', 'Libur Semester Ganjil 2026'],

    // ── SEMESTER GENAP ───────────────────────────────────────────
    ['2027-03-05', null, 'informasi', 'Batas Akhir Yudisium Semester Ganjil 2026'],
    ['2027-03-06', null, 'informasi', 'Pelaporan Nilai PDDIKTI'],
    ['2027-03-08', null, 'libur', 'Hari Raya Nyepi'],
    ['2027-03-09', '2027-03-15', 'libur', 'Hari Raya Idul Fitri'],
    ['2027-03-15', '2027-03-19', 'informasi', 'Persiapan Kuliah Perdana'],
    ['2027-03-22', null, 'informasi', 'Kuliah Perdana Semester Genap'],
    ['2027-03-25', null, 'libur', 'Cuti Bersama'],
    ['2027-03-26', null, 'libur', 'Wafat Isa Almasih'],
    ['2027-03-28', null, 'libur', 'Kebangkitan Isa Almasih'],
    ['2027-04-01', '2027-07-30', 'informasi', 'Pendaftaran & Seleksi Jalur Mandiri'],
    ['2027-05-01', null, 'libur', 'Hari Buruh Internasional'],
    ['2027-05-06', null, 'libur', 'Kenaikan Isa Almasih'],
    ['2027-05-10', '2027-05-14', 'informasi', 'Minggu Pengganti, Perwalian Pra ATS'],
    ['2027-05-17', '2027-05-18', 'libur', 'Hari Raya Idul Adha (awal periode ATS 17–28 Mei)'],
    ['2027-05-19', '2027-05-20', 'libur', 'Hari Raya Waisak (di tengah periode ATS)'],
    ['2027-05-21', '2027-05-28', 'ujian', 'Pelaksanaan ATS Semester Genap (resmi 17–28 Mei; 17–18 libur Idul Adha, 19–20 libur Waisak)'],
    ['2027-06-01', null, 'libur', 'Hari Lahir Pancasila'],
    ['2027-06-06', null, 'libur', 'Tahun Baru Islam'],
    ['2027-06-07', null, 'informasi', 'Minggu ke-9–14: Identifikasi Projek PBL'],
    ['2027-07-19', '2027-07-23', 'informasi', 'Minggu Pengganti, Perwalian Pra AAS & Daftar Ulang'],
    ['2027-07-26', '2027-08-06', 'ujian', 'Pelaksanaan AAS Semester Genap'],
    ['2027-07-30', null, 'informasi', 'Batas Akhir Penerimaan Jalur Mandiri'],
    ['2027-08-02', '2027-09-03', 'informasi', 'Daftar Ulang Semester Ganjil 2027'],
    ['2027-08-15', null, 'libur', 'Maulid Nabi Muhammad SAW'],
    ['2027-08-17', null, 'libur', 'HUT Republik Indonesia'],
    ['2027-08-23', null, 'informasi', 'Publikasi Nilai'],
    ['2027-08-24', '2027-08-25', 'informasi', 'Masa Sanggah'],
    ['2027-08-27', null, 'informasi', 'Kunci Nilai'],
    ['2027-08-30', '2027-09-03', 'informasi', 'Kegiatan PKKMB'],
    ['2027-09-06', '2027-09-10', 'informasi', 'Persiapan Kuliah Perdana'],
    ['2027-09-10', null, 'informasi', 'Batas Akhir Yudisium Semester Genap 2026'],
    ['2027-09-11', null, 'informasi', 'Pelaporan Nilai PDDIKTI'],
    ['2027-09-13', null, 'informasi', 'Kuliah Perdana Semester Ganjil + Penerimaan RPL & PSPPI Genap 2026'],
];

$buat = 0;
$lewati = 0;
foreach ($events as [$mulai, $selesai, $jenis, $ket]) {
    $selesai = $selesai ?: $mulai;
    $ada = AbsensiKalenderLibur::query()
        ->whereDate('tanggal', $mulai)
        ->whereDate('tanggal_selesai', $selesai)
        ->where('keterangan', $ket)
        ->exists();
    if ($ada) {
        $lewati++;
        continue;
    }
    AbsensiKalenderLibur::create([
        'tanggal' => $mulai,
        'tanggal_selesai' => $selesai,
        'keterangan' => $ket,
        'jenis' => $jenis,
        'dampak_absensi' => $jenis === 'libur' ? 'batal_wajib' : 'tetap_wajib',
    ]);
    $buat++;
}

echo "Kalender Akademik TA 2026/2027 — dibuat: {$buat}, dilewati (sudah ada): {$lewati}, total event: " . count($events) . "\n";
