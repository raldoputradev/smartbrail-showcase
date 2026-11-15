<?php

namespace App\Services\AdminAi;

/**
 * Registry tool Asisten AI Admin.
 * kind: read = langsung; write = hanya proposal HITL.
 */
final class AdminAiToolRegistry
{
    /** Copilot baca-saja: tool write tetap terdaftar, tetapi tidak bisa dipanggil. */
    public const READ_ONLY = true;

    /** Aksi tulis yang diaktifkan lewat preview+konfirmasi (HITL) walau READ_ONLY aktif. */
    public const WRITE_TOOLS_ENABLED = [
        'reply_suggestion',
        'approve_user',
        'reject_user',
        'bulk_approve_ready',
        'unlock_user_login',
        'toggle_user_active',
        'draft_pengumuman',
        'create_announcement',
        'toggle_announcement',
        'create_ruangan',
        'toggle_mesin',
        'koreksi_absensi_harian',
        'batalkan_alfa',
        'selesai_tidak_lengkap',
    ];

    public static function writeEnabled(string $tool): bool
    {
        return in_array($tool, self::WRITE_TOOLS_ENABLED, true);
    }

    /**
     * Tool yang dideklarasikan ke model. Hanya agregat yang dikirim ke provider;
     * daftar nama/NIM dikirim langsung ke UI sebagai blok.
     */
    public const COPILOT_TOOLS = [
        'ringkasan_absensi_hari_ini',
        'daftar_absensi_hari_ini',
        'laporan_absensi_periode',
        'cari_mahasiswa',
        'chart_absensi',
        'hitung_pengguna',
        'attention_today',
        'ops_summary',
        'mesin_status',
        'ruangan_status',
        'pengajuan_pending',
        'petugas_list',
        'kalender_agenda',
        'list_pending_approvals',
        'enroll_pending',
        'riwayat_aksi_ai',
        'monitoring_snapshot',
        'log_absensi_cari',
        'approve_user',
        'bulk_approve_ready',
        'draft_pengumuman',
        'create_announcement',
        'create_ruangan',
        'toggle_mesin',
    ];

    /** Dosen/KPS: mahasiswa bimbingan, absensi, rekap, laporan, dan tim PBL. */
    public const COPILOT_TOOLS_DOSEN = [
        'kalender_agenda',
        'ringkasan_absensi_hari_ini',
        'daftar_absensi_hari_ini',
        'laporan_absensi_periode',
        'cari_mahasiswa',
        'chart_absensi',
        'tim_pbl_saya',
        'mahasiswa_bimbingan',
        'pengajuan_ruangan_saya',
        'list_alfa',
        'list_announcements',
        'log_absensi_cari',
    ];

    /** Izin menu dosen yang sudah dipakai halaman dosen untuk data yang sama. */
    private const DOSEN_PERMS = [
        'ringkasan_absensi_hari_ini' => ['dosen_absensi', 'view'],
        'daftar_absensi_hari_ini' => ['dosen_absensi', 'view'],
        'laporan_absensi_periode' => ['dosen_absensi', 'view'],
        'cari_mahasiswa' => ['dosen_mahasiswa', 'view'],
        'chart_absensi' => ['dosen_absensi', 'view'],
        'kalender_agenda' => ['dosen_absensi', 'view'],
        'tim_pbl_saya' => ['dosen_pbl', 'view'],
        'mahasiswa_bimbingan' => ['dosen_mahasiswa', 'view'],
        'pengajuan_ruangan_saya' => ['dosen_pbl', 'view'],
        'list_alfa' => ['dosen_absensi', 'view'],
        'list_announcements' => ['announcements', 'view'],
        'log_absensi_cari' => ['dosen_absensi', 'view'],
    ];

    /**
     * Tool Copilot untuk role (dari sesi, bukan dari argumen model/klien).
     *
     * @return list<string>
     */
    public static function copilotToolsFor(string $role): array
    {
        return match ($role) {
            'admin', 'laboran' => self::COPILOT_TOOLS,
            'dosen' => self::COPILOT_TOOLS_DOSEN,
            default => [],
        };
    }

    /**
     * Izin menu yang menentukan akses tool untuk role.
     *
     * @return array{0: string, 1: string}|null null = role tidak boleh memakai tool ini
     */
    public static function permFor(string $tool, string $role): ?array
    {
        $meta = self::get($tool);
        if (! $meta) {
            return null;
        }

        return match ($role) {
            'admin', 'laboran' => $meta['perm'],
            'dosen' => self::DOSEN_PERMS[$tool] ?? null,
            default => null,
        };
    }

    /**
     * @return array<string, array{
     *   kind: string,
     *   perm: array{0: string, 1: string},
     *   description: string,
     *   parameters: array<string, mixed>,
     *   dangerous?: bool,
     *   title: string
     * }>
     */
    public static function all(): array
    {
        return [
            'ringkasan_absensi_hari_ini' => [
                'kind' => 'read',
                'perm' => ['attendance', 'view'],
                'title' => 'Rekap absensi hari ini',
                'description' => 'Ringkasan rekap absensi HARI INI (hadir, lengkap, terlambat, tidak lengkap, alfa, belum tap) dari halaman Cek Absensi. Filter opsional kelas/tim/sesi.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'kelas' => ['type' => 'string'],
                        'tim' => ['type' => 'string'],
                        'sesi' => ['type' => 'string', 'enum' => ['pagi', 'malam']],
                    ],
                    'required' => [],
                ],
            ],
            'daftar_absensi_hari_ini' => [
                'kind' => 'read',
                'perm' => ['attendance', 'view'],
                'title' => 'Daftar absensi hari ini',
                'description' => 'Tabel mahasiswa HARI INI per status: tidak_lengkap (tidak lengkap/hanya masuk), alfa, hadir, atau semua. Tabel tampil langsung di UI; kamu hanya menerima jumlahnya.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'status' => ['type' => 'string', 'enum' => ['tidak_lengkap', 'alfa', 'hadir', 'semua']],
                        'kelas' => ['type' => 'string'],
                        'tim' => ['type' => 'string'],
                        'sesi' => ['type' => 'string', 'enum' => ['pagi', 'malam']],
                    ],
                    'required' => ['status'],
                ],
            ],
            'laporan_absensi_periode' => [
                'kind' => 'read',
                'perm' => ['reports', 'view'],
                'title' => 'Laporan absensi periode',
                'description' => 'Laporan periodik dari menu Laporan: ringkasan hadir lengkap/tidak lengkap/alfa + peringkat + tautan unduh Excel/Word. WAJIB isi periode. Jika pengguna belum menyebut periode, panggil tanpa periode agar UI menampilkan pilihan, lalu tanyakan satu pertanyaan singkat.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'periode' => ['type' => 'string', 'enum' => ['minggu_ini', 'minggu_lalu', 'bulan_ini', 'bulan_lalu', 'tahun_bulan', 'custom']],
                        'bulan' => ['type' => 'string', 'description' => 'YYYY-MM untuk periode tahun_bulan'],
                        'tanggal_mulai' => ['type' => 'string', 'description' => 'YYYY-MM-DD untuk custom'],
                        'tanggal_akhir' => ['type' => 'string', 'description' => 'YYYY-MM-DD untuk custom'],
                        'kelas' => ['type' => 'string'],
                        'tim' => ['type' => 'string'],
                        'rekor' => ['type' => 'string', 'enum' => ['terajin', 'alfa', 'jarang', 'tidak_lengkap'], 'description' => 'Peringkat yang ditampilkan'],
                    ],
                    'required' => [],
                ],
            ],
            'cari_mahasiswa' => [
                'kind' => 'read',
                'perm' => ['users', 'view'],
                'title' => 'Cari mahasiswa',
                'description' => 'Cari mahasiswa berdasarkan nama atau NIM (min 2 karakter). Hasil tampil sebagai tabel di UI; kamu hanya menerima jumlah.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'q' => ['type' => 'string', 'description' => 'Nama atau NIM'],
                    ],
                    'required' => ['q'],
                ],
            ],
            'tim_pbl_saya' => [
                'kind' => 'read',
                'perm' => ['users', 'view'],
                'title' => 'Tim PBL bimbingan',
                'description' => 'DOSEN: daftar tim PBL aktif yang dibimbing pengguna (manpro/co-manpro) beserta jumlah anggota. WAJIB dipakai untuk pertanyaan tim PBL/bimbingan/anggota tim. Isi tim untuk daftar anggota satu tim.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'tim' => ['type' => 'string', 'description' => 'Nama/kode tim untuk daftar anggota (opsional)'],
                    ],
                    'required' => [],
                ],
            ],
            'mahasiswa_bimbingan' => [
                'kind' => 'read',
                'perm' => ['users', 'view'],
                'title' => 'Mahasiswa bimbingan',
                'description' => 'DOSEN: daftar seluruh mahasiswa dalam lingkup bimbingan (kelas yang ditugaskan + tim PBL). Pakai untuk "siapa mahasiswa bimbingan saya", "berapa mahasiswa saya", atau daftar tanpa kata kunci. Filter opsional kelas/tim.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'kelas' => ['type' => 'string'],
                        'tim' => ['type' => 'string'],
                    ],
                    'required' => [],
                ],
            ],
            'pengajuan_ruangan_saya' => [
                'kind' => 'read',
                'perm' => ['users', 'view'],
                'title' => 'Pengajuan ruangan tim',
                'description' => 'DOSEN: status pengajuan pemakaian ruangan dari tim PBL yang Anda bimbing (menunggu/disetujui/ditolak/dibatalkan). Pakai untuk "status pengajuan ruangan tim saya".',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'status' => ['type' => 'string', 'enum' => ['semua', 'menunggu', 'disetujui', 'ditolak', 'dibatalkan']],
                    ],
                    'required' => [],
                ],
            ],
            'ops_summary' => [
                'kind' => 'read',
                'perm' => ['dashboard', 'view'],
                'title' => 'Ringkasan operasional',
                'description' => 'Ringkas antrian approval, saran baru, enroll sidik jari belum, status mesin, dan tap absensi minggu ini.',
                'parameters' => ['type' => 'object', 'properties' => (object) [], 'required' => []],
            ],
            'mesin_status' => [
                'kind' => 'read',
                'perm' => ['mesin', 'view'],
                'title' => 'Status mesin IoT',
                'description' => 'Daftar mesin IoT: online/offline/nonaktif (maks 30 baris).',
                'parameters' => ['type' => 'object', 'properties' => (object) [], 'required' => []],
            ],
            'enroll_pending' => [
                'kind' => 'read',
                'perm' => ['fingerprint', 'view'],
                'title' => 'Belum enroll fingerprint',
                'description' => 'Hitung dan sample mahasiswa aktif yang belum enroll sidik jari.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => ['type' => 'integer', 'description' => 'Maks sample nama (default 15, max 25)'],
                    ],
                    'required' => [],
                ],
            ],
            'rekap_absensi' => [
                'kind' => 'read',
                'perm' => ['attendance', 'view'],
                'title' => 'Rekap absensi',
                'description' => 'Agregat tap absensi untuk periode hari/minggu/bulan.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'mode' => ['type' => 'string', 'enum' => ['hari', 'minggu', 'bulan'], 'description' => 'Periode'],
                        'tanggal' => ['type' => 'string', 'description' => 'Anchor YYYY-MM-DD (default hari ini)'],
                    ],
                    'required' => [],
                ],
            ],
            'chart_absensi' => [
                'kind' => 'read',
                'perm' => ['attendance', 'view'],
                'title' => 'Grafik absensi',
                'description' => 'WAJIB untuk grafik visual tap absensi (Chart.js line/bar). mode: 7hari|minggu|bulan|tahun|custom. Filter opsional kelas/tim. custom butuh tanggal_mulai+tanggal_akhir. Jangan ganti dengan tabel markdown. Kosong tetap tampilkan grafik (nilai 0) untuk kesiapan data ke depan.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'mode' => ['type' => 'string', 'enum' => ['7hari', 'minggu', 'bulan', 'tahun', 'custom']],
                        'tanggal' => ['type' => 'string', 'description' => 'Anchor akhir YYYY-MM-DD (opsional)'],
                        'tanggal_mulai' => ['type' => 'string', 'description' => 'Untuk mode custom'],
                        'tanggal_akhir' => ['type' => 'string', 'description' => 'Untuk mode custom'],
                        'kelas' => ['type' => 'string'],
                        'tim' => ['type' => 'string', 'description' => 'Filter tim/PBL'],
                        'prefer_data' => ['type' => 'boolean', 'description' => 'Hanya untuk mode 7hari: geser ke minggu ber-data jika kosong'],
                        'chart_type' => ['type' => 'string', 'enum' => ['line', 'bar']],
                    ],
                    'required' => [],
                ],
            ],
            'list_pending_approvals' => [
                'kind' => 'read',
                'perm' => ['users', 'view'],
                'title' => 'Daftar pending approval',
                'description' => 'List akun siap disetujui (OTP verified, pending). Maks 20.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => ['type' => 'integer'],
                    ],
                    'required' => [],
                ],
            ],
            'list_suggestions' => [
                'kind' => 'read',
                'perm' => ['suggestions', 'view'],
                'title' => 'Antrian saran',
                'description' => 'Saran status baru / belum ditanggapi (maks 15).',
                'parameters' => ['type' => 'object', 'properties' => (object) [], 'required' => []],
            ],
            'pbl_readiness' => [
                'kind' => 'read',
                'perm' => ['users', 'view'],
                'title' => 'Kesiapan data PBL',
                'description' => 'Checklist readiness proyek PBL untuk absensi Kamis.',
                'parameters' => ['type' => 'object', 'properties' => (object) [], 'required' => []],
            ],
            'cari_pengguna' => [
                'kind' => 'read',
                'perm' => ['users', 'view'],
                'title' => 'Cari pengguna',
                'description' => 'Cari mahasiswa/dosen/laboran by nama, NITK/NIDN, atau email. Jika q kosong/"list" dan role diisi, daftar user role itu (maks 15). Tanpa password. JANGAN pakai tool ini untuk menghitung total — gunakan hitung_pengguna.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'q' => ['type' => 'string', 'description' => 'Kata kunci nama / NITK / email, atau kosong/list untuk daftar'],
                        'role' => ['type' => 'string', 'enum' => ['semua', 'admin', 'mahasiswa', 'dosen', 'laboran'], 'description' => 'Filter role (default semua)'],
                    ],
                    'required' => [],
                ],
            ],
            'hitung_pengguna' => [
                'kind' => 'read',
                'perm' => ['users', 'view'],
                'title' => 'Hitung pengguna',
                'description' => 'Hitung total data user di DB: semua / mahasiswa / dosen / laboran. Sertakan breakdown aktif, pending approval, nonaktif. Pakai ini untuk pertanyaan "berapa banyak", "total", "jumlah" mahasiswa/dosen.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'role' => ['type' => 'string', 'enum' => ['semua', 'admin', 'mahasiswa', 'dosen', 'laboran'], 'description' => 'Fokus role (default semua = ringkasan semua role)'],
                    ],
                    'required' => [],
                ],
            ],
            'monitoring_snapshot' => [
                'kind' => 'read',
                'perm' => ['monitoring', 'view'],
                'title' => 'Snapshot monitoring',
                'description' => 'Metrik live: mesin online/offline, tap absensi hari ini, 8 aktivitas terbaru.',
                'parameters' => ['type' => 'object', 'properties' => (object) [], 'required' => []],
            ],
            'log_absensi_cari' => [
                'kind' => 'read',
                'perm' => ['log_absensi', 'view'],
                'title' => 'Cari log absensi',
                'description' => 'Cari tap absensi mentah by nama/NITK, mesin, rentang tanggal. Default hari ini. Maks 20 baris.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'q' => ['type' => 'string'],
                        'id_mesin' => ['type' => 'string'],
                        'tanggal_mulai' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                        'tanggal_akhir' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                    ],
                    'required' => [],
                ],
            ],
            'list_alfa' => [
                'kind' => 'read',
                'perm' => ['attendance', 'view'],
                'title' => 'Daftar alfa',
                'description' => 'Mahasiswa alfa aktif pada tanggal (default hari ini). Opsional filter kelas/tim. Maks 25.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'tanggal' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                        'kelas' => ['type' => 'string'],
                        'tim' => ['type' => 'string'],
                    ],
                    'required' => [],
                ],
            ],
            'riwayat_pengguna' => [
                'kind' => 'read',
                'perm' => ['reports', 'view'],
                'title' => 'Riwayat pengguna',
                'description' => 'Ringkas tap absensi satu user untuk N hari terakhir (default 7, max 31).',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'user_id' => ['type' => 'integer'],
                        'hari' => ['type' => 'integer', 'description' => 'Jumlah hari ke belakang'],
                    ],
                    'required' => ['user_id'],
                ],
            ],
            'list_announcements' => [
                'kind' => 'read',
                'perm' => ['announcements', 'view'],
                'title' => 'Daftar pengumuman',
                'description' => 'Pengumuman aktif/draft terbaru (maks 15).',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'status' => ['type' => 'string', 'enum' => ['semua', 'aktif', 'draft']],
                    ],
                    'required' => [],
                ],
            ],
            'fingerprint_sync_report' => [
                'kind' => 'read',
                'perm' => ['fingerprint', 'view'],
                'title' => 'Laporan sync fingerprint',
                'description' => 'Stat sync hari ini + 10 log sync terakhir (tanpa payload template).',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'id_mesin' => ['type' => 'string', 'description' => 'Filter id_mesin opsional'],
                    ],
                    'required' => [],
                ],
            ],

            'ruangan_status' => [
                'kind' => 'read',
                'perm' => ['ruangan', 'view'],
                'title' => 'Status ruangan',
                'description' => 'Daftar ruangan beserta status aktif, kapasitas, fasilitas, perangkat terikat, dan pemakaian. Filter q opsional.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => ['q' => ['type' => 'string', 'description' => 'Filter nama/kode ruangan']],
                    'required' => [],
                ],
            ],
            'pengajuan_pending' => [
                'kind' => 'read',
                'perm' => ['ruangan_persetujuan', 'view'],
                'title' => 'Pengajuan ruangan menunggu',
                'description' => 'Antrian pengajuan ruangan yang menunggu keputusan (maks 20): pemohon, ruangan, tanggal, jam, keperluan.',
                'parameters' => ['type' => 'object', 'properties' => (object) [], 'required' => []],
            ],
            'petugas_list' => [
                'kind' => 'read',
                'perm' => ['petugas', 'view'],
                'title' => 'Daftar petugas ruangan',
                'description' => 'Petugas/CS non-portal yang disinkron ke mesin: nama, status, ruangan akses. Bukan akun portal.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => ['q' => ['type' => 'string', 'description' => 'Filter nama petugas']],
                    'required' => [],
                ],
            ],
            'kalender_agenda' => [
                'kind' => 'read',
                'perm' => ['attendance', 'view'],
                'title' => 'Agenda kalender',
                'description' => 'Agenda kalender pusat (libur, peringatan, ujian, perubahan jam, informasi, agenda) untuk bulan tertentu atau 60 hari ke depan.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => ['bulan' => ['type' => 'string', 'description' => 'Format YYYY-MM; kosong = 60 hari ke depan']],
                    'required' => [],
                ],
            ],
            'riwayat_aksi_ai' => [
                'kind' => 'read',
                'perm' => ['dashboard', 'view'],
                'title' => 'Riwayat aksi AI',
                'description' => 'Laporan aksi yang pernah diusulkan/dijalankan asisten untuk Anda: status pending/confirmed/failed/cancelled.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'status' => ['type' => 'string', 'enum' => ['pending', 'confirmed', 'failed', 'cancelled']],
                        'limit' => ['type' => 'integer', 'description' => 'Maks 20'],
                    ],
                    'required' => [],
                ],
            ],

            // Write — proposal only
            'create_ruangan' => [
                'kind' => 'write',
                'perm' => ['ruangan', 'create'],
                'title' => 'Daftarkan ruangan baru',
                'description' => 'Usulkan mendaftarkan ruangan baru (butuh konfirmasi). Berikan nama, opsional kode/kapasitas/fasilitas.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'nama' => ['type' => 'string', 'description' => 'Nama ruangan'],
                        'kode' => ['type' => 'string', 'description' => 'Kode singkat, mis. LAB-301'],
                        'kapasitas' => ['type' => 'integer'],
                        'fasilitas' => ['type' => 'string', 'description' => 'Daftar fasilitas dipisah koma'],
                    ],
                    'required' => ['nama'],
                ],
            ],
            'approve_user' => [
                'kind' => 'write',
                'perm' => ['users', 'update'],
                'title' => 'Setujui akun',
                'description' => 'Usulkan menyetujui satu akun user (butuh konfirmasi admin). Berikan user_id.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'user_id' => ['type' => 'integer', 'description' => 'ID user yang akan disetujui'],
                    ],
                    'required' => ['user_id'],
                ],
            ],
            'reject_user' => [
                'kind' => 'write',
                'perm' => ['users', 'update'],
                'title' => 'Tolak akun',
                'description' => 'Usulkan menolak akun pending. Wajib alasan singkat.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'user_id' => ['type' => 'integer'],
                        'approval_note' => ['type' => 'string', 'description' => 'Alasan penolakan'],
                    ],
                    'required' => ['user_id', 'approval_note'],
                ],
            ],
            'bulk_approve_ready' => [
                'kind' => 'write',
                'perm' => ['users', 'update'],
                'title' => 'Setujui banyak akun',
                'description' => 'Usulkan menyetujui beberapa akun yang siap approval. Berikan user_ids, ATAU all=true untuk semua antrian siap (maks 20). Cocok untuk "setujui semua akun pending".',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'user_ids' => [
                            'type' => 'array',
                            'items' => ['type' => 'integer'],
                            'description' => 'Daftar ID user (opsional jika all=true)',
                        ],
                        'all' => [
                            'type' => 'boolean',
                            'description' => 'true = ambil semua akun siap approval (maks 20)',
                        ],
                    ],
                    'required' => [],
                ],
            ],
            'reply_suggestion' => [
                'kind' => 'write',
                'perm' => ['suggestions', 'update'],
                'title' => 'Balas saran',
                'description' => 'Usulkan balasan admin untuk satu saran.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'suggestion_id' => ['type' => 'integer'],
                        'admin_reply' => ['type' => 'string'],
                    ],
                    'required' => ['suggestion_id', 'admin_reply'],
                ],
            ],
            'create_announcement' => [
                'kind' => 'write',
                'perm' => ['announcements', 'create'],
                'title' => 'Buat pengumuman',
                'description' => 'Usulkan draft/publish pengumuman. action: draft atau publish.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'judul' => ['type' => 'string'],
                        'isi' => ['type' => 'string'],
                        'target' => ['type' => 'string', 'enum' => ['semua', 'dosen', 'mahasiswa']],
                        'prioritas' => ['type' => 'string', 'enum' => ['normal', 'penting', 'urgent']],
                        'action' => ['type' => 'string', 'enum' => ['draft', 'publish']],
                    ],
                    'required' => ['judul', 'isi', 'target', 'prioritas'],
                ],
            ],
            'toggle_mesin' => [
                'kind' => 'write',
                'perm' => ['mesin', 'update'],
                'title' => 'Toggle aktif mesin',
                'description' => 'Usulkan mengaktifkan/nonaktifkan mesin IoT berdasarkan id mesin (primary key).',
                'dangerous' => true,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'mesin_id' => ['type' => 'integer', 'description' => 'PK mesin_iot.id'],
                    ],
                    'required' => ['mesin_id'],
                ],
            ],
            'unlock_user_login' => [
                'kind' => 'write',
                'perm' => ['users', 'update'],
                'title' => 'Buka kunci login',
                'description' => 'Usulkan membuka kunci login user yang terkunci keamanan.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'user_id' => ['type' => 'integer'],
                    ],
                    'required' => ['user_id'],
                ],
            ],
            'fingerprint_sync_burst' => [
                'kind' => 'write',
                'perm' => ['fingerprint', 'update'],
                'title' => 'Distribusi sync fingerprint',
                'description' => 'Usulkan mengaktifkan burst distribusi template fingerprint ke mesin (20 menit).',
                'dangerous' => true,
                'parameters' => ['type' => 'object', 'properties' => (object) [], 'required' => []],
            ],
            'toggle_user_active' => [
                'kind' => 'write',
                'perm' => ['users', 'update'],
                'title' => 'Aktif/nonaktif akun',
                'description' => 'Usulkan mengaktifkan atau menonaktifkan akun user (butuh konfirmasi).',
                'dangerous' => true,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'user_id' => ['type' => 'integer'],
                        'set_active' => ['type' => 'boolean', 'description' => 'true=aktifkan, false=nonaktifkan'],
                    ],
                    'required' => ['user_id', 'set_active'],
                ],
            ],
            'toggle_announcement' => [
                'kind' => 'write',
                'perm' => ['announcements', 'update'],
                'title' => 'Toggle pengumuman',
                'description' => 'Usulkan publish/unpublish pengumuman by id.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'announcement_id' => ['type' => 'integer'],
                    ],
                    'required' => ['announcement_id'],
                ],
            ],

            'attention_today' => [
                'kind' => 'read',
                'perm' => ['dashboard', 'view'],
                'title' => 'Perhatian hari ini',
                'description' => 'Ringkas item yang perlu perhatian admin hari ini: pending approval, saran baru, alfa, tidak lengkap, mesin offline, enroll, slot maintenance. Pakai untuk "apa yang perlu diperhatikan".',
                'parameters' => ['type' => 'object', 'properties' => (object) [], 'required' => []],
            ],
            'laporan_periodik_ringkas' => [
                'kind' => 'read',
                'perm' => ['reports', 'view'],
                'title' => 'Laporan periodik ringkas',
                'description' => 'Ringkas laporan periodik mahasiswa: summary hadir/alfa/TL + ranking singkat (tanpa export file).',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'periode' => ['type' => 'string', 'enum' => ['minggu_ini', 'minggu_lalu', 'bulan_ini', 'bulan_lalu']],
                        'rekor' => ['type' => 'string', 'enum' => ['terajin', 'alfa', 'jarang', 'tidak_lengkap']],
                        'kelas' => ['type' => 'string'],
                        'tim' => ['type' => 'string'],
                    ],
                    'required' => [],
                ],
            ],
            'koreksi_absensi_harian' => [
                'kind' => 'write',
                'perm' => ['attendance', 'update'],
                'title' => 'Koreksi absensi harian',
                'description' => 'Usulkan koreksi status AbsensiHarian (HITL). Berikan harian_id ATAU (user_id + tanggal + sesi). status: lengkap|tidak_lengkap|alfa|masuk|belum.',
                'dangerous' => true,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'harian_id' => ['type' => 'integer'],
                        'user_id' => ['type' => 'integer'],
                        'tanggal' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                        'sesi' => ['type' => 'string'],
                        'status' => ['type' => 'string', 'enum' => ['lengkap', 'tidak_lengkap', 'alfa', 'masuk', 'belum']],
                        'catatan_koreksi' => ['type' => 'string'],
                    ],
                    'required' => ['status'],
                ],
            ],
            'batalkan_alfa' => [
                'kind' => 'write',
                'perm' => ['attendance', 'update'],
                'title' => 'Batalkan alfa',
                'description' => 'Usulkan membatalkan AbsensiAlfa aktif (HITL). Berikan alfa_id ATAU (user_id + tanggal + sesi).',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'alfa_id' => ['type' => 'integer'],
                        'user_id' => ['type' => 'integer'],
                        'tanggal' => ['type' => 'string'],
                        'sesi' => ['type' => 'string'],
                        'catatan' => ['type' => 'string'],
                    ],
                    'required' => [],
                ],
            ],
            'selesai_tidak_lengkap' => [
                'kind' => 'write',
                'perm' => ['attendance', 'update'],
                'title' => 'Selesaikan tidak lengkap',
                'description' => 'Usulkan menandai kasus tidak lengkap selesai (HITL). harian_id wajib (status harus tidak_lengkap).',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'harian_id' => ['type' => 'integer'],
                        'catatan_koreksi' => ['type' => 'string'],
                    ],
                    'required' => ['harian_id'],
                ],
            ],
            'draft_pengumuman' => [
                'kind' => 'write',
                'perm' => ['announcements', 'create'],
                'title' => 'Draft pengumuman dari topik',
                'description' => 'Buat DRAFT pengumuman dari topik chat (HITL, belum publish). Cocok: "buat pengumuman tentang …".',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'topik' => ['type' => 'string', 'description' => 'Topik/isi singkat dari percakapan'],
                        'target' => ['type' => 'string', 'enum' => ['semua', 'dosen', 'mahasiswa']],
                        'prioritas' => ['type' => 'string', 'enum' => ['normal', 'penting', 'urgent']],
                    ],
                    'required' => ['topik'],
                ],
            ],
        ];
    }

    /**
     * @return list<array{name: string, description: string, parameters: array<string, mixed>}>
     */
    public static function geminiDeclarationsForUser(?\App\Models\User $user = null): array
    {
        $user ??= \Illuminate\Support\Facades\Auth::user();
        if (! $user) {
            return [];
        }
        $out = [];
        foreach (self::all() as $name => $meta) {
            if (! in_array($name, self::copilotToolsFor($user->role), true) || (self::READ_ONLY && $meta['kind'] !== 'read' && ! self::writeEnabled($name))) {
                continue;
            }
            $perm = self::permFor($name, $user->role);
            if (! $perm || ! \App\Support\PermissionRegistry::can($user->role, $perm[0], $perm[1])) {
                continue;
            }
            $out[] = [
                'name' => $name,
                'description' => $meta['description'],
                'parameters' => $meta['parameters'],
            ];
        }

        return $out;
    }

    public static function get(string $name): ?array
    {
        return self::all()[$name] ?? null;
    }
}
