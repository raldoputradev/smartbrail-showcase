export interface AuthUser {
    id: number;
    nama_lengkap: string;
    nitk_nidn: string | null;
    role: string;
    kelas: string | null;
    tim: string | null;
    shift: string | null;
    email: string | null;
    no_hp: string | null;
    finger_id: number | null;
    must_change_password: boolean;
    has_fingerprint: boolean | null;
    profile_photo_url: string | null;
}

export interface NotifItem {
    uid: string;
    id?: number;
    kind: string;
    category_label?: string;
    priority?: string;
    title: string;
    excerpt: string;
    body?: string;
    meta?: string | null;
    timestamp?: string | null;
    time_human: string;
    is_read: boolean;
    read_url?: string | null;
    action_url?: string | null;
    action_label?: string | null;
}

export interface NotifState {
    unread_count: number;
    /** Balasan laboran atas laporan yang belum dibaca — penanda sidebar Riwayat Laporan. */
    reply_unread_count?: number;
    has_more: boolean;
    items: NotifItem[];
}

export interface SharedProps {
    [key: string]: any;
    auth: { user: AuthUser | null };
    branding?: { logo_url: string; app_name: string };
    flash: {
        login_welcome?: boolean | null;
        success?: string | null;
        error?: string | null;
        status?: string | null;
        terkirim?: {
            id: number; ruangan: string; tanggal: string;
            jam_mulai: string; jam_selesai: string; tujuan: string; peserta: string[];
        } | null;
    };
    navNotificationState?: NotifState;
    /** Label fitur sidebar dari admin — key => 'new' | 'soon' | 'off' */
    features?: Record<string, string>;
    /** Lampiran/gambar di asisten AI — false = dinonaktifkan semester ini. */
    ai_attachments?: boolean;
    preview?: { ketersediaan_url?: string } | null;
    reverb?: {
        key: string;
        host: string;
        port: number;
        scheme: string;
        auth_url: string;
    } | null;
}

export interface Pengajuan {
    id: number;
    status: string;
    status_efektif: string;
    tujuan: string;
    tanggal: string;
    jam_mulai: string;
    jam_selesai: string;
    peserta: number[] | null;
    snapshot: { peserta_nama?: string[] } | null;
    pengganti_mulai: string | null;
    pengganti_selesai: string | null;
    pengganti_catatan: string | null;
    alasan_keputusan: string | null;
    alasan_status?: string | null;
    diputus_pada: string | null;
    diputus_oleh_nama?: string | null;
    diputus_oleh_role?: string | null;
    tim_nama?: string | null;
    pengaju_nama?: string | null;
    ruangan?: { id: number; nama: string; kode: string; foto_url?: string | null } | null;
    izin?: Array<{ id: number; status: string; mulai: string; selesai: string }>;
}

export interface Tim {
    id: number;
    nama: string;
    kode: string;
    manpro: string | null;
    co_manpro: string | null;
    shift_sesi: string | null;
}

export interface Anggota {
    id: number;
    nama_lengkap: string;
    nitk_nidn: string | null;
    kelas?: string | null;
    role?: string | null;
    profile_photo_path?: string | null;
    profile_photo_url?: string | null;
    foto?: string | null;
}

export interface RuanganAturan {
    hari: number[];
    jam_buka: string;
    jam_tutup: string;
    min_advance_days: number;
    max_advance_days: number;
    max_duration_minutes: number;
    max_peserta: number | null;
    max_pending_per_team: number;
    durasi_opsi?: number[];
}

export interface Ruangan {
    id: number;
    kode: string;
    nama: string;
    lokasi: string | null;
    aktif: boolean;
    kapasitas: number | null;
    fasilitas: string[] | null;
    foto: string | null;
    foto_url?: string | null;
    aturan: RuanganAturan;
}

export interface RuanganEntry {
    model: Ruangan;
    aturan: RuanganAturan;
}

export interface Slot {
    mulai: string;
    selesai: string;
    status: 'tersedia' | 'dipesan' | 'petugas' | 'lewat' | 'diluar_jam';
    /** Nama tim/pengaju pemesan — diisi saat status 'dipesan' */
    info?: string | null;
}

export interface KetersediaanResp {
    tanggal: string;
    boleh: boolean;
    alasan: string | null;
    habis_alasan?: string | null;
    hari_label?: string | null;
    tanggal_tersedia_berikutnya?: string | null;
    jam_buka: string;
    jam_tutup: string;
    hari: number[];
    durasi_opsi?: number[];
    slots: Slot[];
    min_advance_days: number;
    max_advance_days: number;
    max_duration_minutes: number;
}

export interface CalDay {
    tanggal: string;
    tanggal_human: string;
    day_num: number;
    dow: number;
    hari_nama: string;
    is_today: boolean;
    is_wajib: boolean;
    is_libur: boolean;
    status_hari: string;
    sumber_label: string;
    jam_masuk: string;
    jam_pulang: string;
    label_jadwal: string;
    label_status_hari: string;
    agenda: Array<{ id: number; jenis: string; jenis_label: string; dampak_label: string; keterangan: string }>;
    ruangan: Array<{ ruangan: string; jam: string; status: string; status_label: string }>;
    absensi: {
        kelengkapan_kode: string;
        kelengkapan_label: string;
        kelengkapan_tone: string;
        jam_masuk: string | null;
        jam_pulang: string | null;
        ketepatan_masuk_label: string | null;
        ketepatan_pulang_label: string | null;
        jadwal_konsisten: boolean;
    } | null;
}

export interface LaporanItem {
    id: number;
    ref: string;
    judul: string;
    kategori: 'absensi' | 'akun' | 'saran';
    kategori_label: string;
    tanggal_absensi: string | null;
    status: 'baru' | 'dibaca' | 'ditanggapi';
    status_label: string;
    tanggal_dikirim: string | null;
    tanggal_dikirim_tgl: string | null;
    cuplikan: string;
    pesan: string;
    lampiran_url: string | null;
    admin_reply: string | null;
    admin_replied_at: string | null;
    balasan_belum_dibaca: boolean;
}

export interface LaporanStats {
    total: number;
    baru: number;
    dibaca: number;
    ditanggapi: number;
}

export interface Paginated<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: Array<{ url: string | null; label: string; active: boolean }>;
}

export interface CalendarData {
    bulan: string;
    bulan_label: string;
    prev?: string;
    next?: string;
    weeks_start?: number;
    days: CalDay[];
    agenda_bulan?: Array<{ id: number; jenis_label: string; keterangan: string; tanggal: string }>;
    agendas?: Array<{ id: number; jenis: string; jenis_label: string; keterangan: string; tanggal: string; tanggal_selesai?: string | null }>;
}
