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

export interface SharedProps {
    [key: string]: unknown;
    auth: { user: AuthUser | null };
    branding?: { logo_url: string; app_name: string };
    flash: {
        login_welcome?: boolean | null;
        success?: string | null;
        error?: string | null;
        info?: string | null;
        status?: string | null;
        activation_once?: {
            nama: string; nitk: string | null; jenis: string;
            code: string; expires_at: string; entry_url: string;
        } | null;
    };
    reverb?: {
        key: string;
        host: string;
        port: number;
        scheme: string;
        auth_url: string;
    } | null;
}

/** Item tautan sidebar — difilter permission di backend. */
export interface SidebarLink {
    kind: 'link';
    label: string;
    icon: string;
    url: string;
    active: boolean;
    badge?: number | null;
    /** Chip teks kecil di sisi kanan item (mis. "Beta"). */
    tag?: string | null;
    /** true = placeholder "segera hadir" — klik memunculkan info, bukan navigasi. */
    soon?: boolean;
}

/** Grup submenu collapsible — anak-anaknya juga difilter permission di backend. */
export interface SidebarGroup {
    kind: 'group';
    key: string;
    label: string;
    icon: string;
    /** true bila salah satu anak adalah halaman aktif */
    active: boolean;
    children: SidebarLink[];
}

export interface SidebarDivider {
    kind: 'divider';
    key: string;
}

export type SidebarNode = SidebarLink | SidebarGroup | SidebarDivider;

/** Seksi sidebar; title null = grup top-level tanpa judul seksi. */
export interface SidebarSection {
    title: string | null;
    nodes: SidebarNode[];
}

export interface DashboardStats {
    total_pengguna: number;
    pengguna_aktif: number;
    pengguna_pending: number;
    pengguna_online: number;
    pengguna_baru_30d: number;
    sidik_jari_terdaftar: number;
    ruangan_aktif: number;
    mesin_aktif: number;
    mesin_total: number;
    mesin_online: number;
    mesin_meta: string;
    pengajuan_pending: number;
}

export interface LineSeries {
    key: string;
    label: string;
    color: string;
    data: number[];
}

export interface LineChartData {
    labels: string[];
    series: LineSeries[];
    subtitle?: string;
}

export interface DonutData {
    labels: string[];
    values: number[];
    colors: string[];
    total: number;
    /** Label pusat donut (default: "Pengguna") */
    center_label?: string;
}

export interface FeedTap {
    waktu: string;
    nama: string;
    nim: string;
    kelas_tim: string;
    foto: string | null;
}

export interface PengajuanItem {
    pemohon: string;
    ruangan: string;
    tanggal: string;
    jam: string;
    status: string;
    foto: string | null;
}

export interface StatusItem {
    nama: string;
    status: string;
    icon: string;
}

export interface QuickAction {
    label: string;
    url: string;
    icon: string;
}

/** Item antrian operasional pada lonceng (dari StaffNotificationService). */
export interface NotifOpsItem {
    id: string;
    title: string;
    body: string;
    url: string;
    fingerprint?: string | null;
}

/** Pengumuman belum dibaca pada lonceng. */
export interface NotifAnnouncement {
    id: number;
    judul: string;
    prioritas: string;
    isi: string;
    author: string;
    waktu: string | null;
}

/** Payload lonceng — sumber data sama dengan NotificationComposer (Blade). */
export interface AdminNotif {
    ops: NotifOpsItem[];
    ops_count: number;
    announcements: NotifAnnouncement[];
    unread: number;
    total: number;
    urls: {
        read_all: string;
        read_one: string;
        ops_dismiss: string;
        announcements: string;
        notifications: string;
    };
}

/* ── Asisten AI ── */
export interface AiConversationItem {
    id: number;
    title: string;
    last_message_at: string | null;
}

export interface AiActionCard {
    label: string;
    desc: string;
    icon: string;
    message?: string;
    fill?: string;
}

export interface AiExample {
    icon: string;
    text: string;
}

export interface AiChartPayload {
    title: string;
    labels: string[];
    values: number[];
    color: string;
    type: 'line' | 'bar' | string;
    total?: number;
    note?: string | null;
    subtitle?: string;
}

export interface AiStatItem {
    label: string;
    value: string | number;
    hint?: string | null;
}

export interface AiTableBlock {
    columns: string[];
    rows: (string | number | null)[][];
    note?: string | null;
}

export interface AiLinkItem {
    label: string;
    href: string;
    kind?: 'download' | 'open' | string;
}

export interface AiProposalPayload {
    id: number;
    tool: string;
    title: string;
    summary: string;
    dangerous?: boolean;
    status?: string;
    expires_at?: string;
    count?: number;
    action_label?: string;
}

export type AiBlock =
    | { type: 'chart'; chart: AiChartPayload }
    | { type: 'archived_action'; title: string; status: string }
    | { type: 'stats'; items: AiStatItem[]; note?: string | null }
    | { type: 'table'; table: AiTableBlock; columns?: string[]; rows?: (string | number | null)[][] }
    | { type: 'links'; items: AiLinkItem[] }
    | { type: 'options'; title?: string; items: { label: string; message: string }[] }
    | { type: 'proposal'; proposal: AiProposalPayload }
    | { type: string; [k: string]: unknown };

export interface AiMessageItem {
    id?: number;
    role: 'user' | 'assistant';
    content: string;
    status?: string;
    error?: string | null;
    blocks?: AiBlock[];
    attachments?: { id: string; name: string; image?: boolean }[];
    created_at?: string;
    client_id?: string;
}

export interface AsistenAiProps {
    menu: SidebarSection[];
    notif: AdminNotif;
    ai_enabled: boolean;
    greeting_name: string;
    conversations: AiConversationItem[];
    actions: AiActionCard[];
    examples: AiExample[];
    initial_conversation: number | null;
    cp: {
        stream: string;
        attachment?: string;
        attachments_enabled?: boolean;
        conversations: string;
        conversation_store: string;
        proposal_confirm?: string;
        proposal_cancel?: string;
        import_page?: string;
        role?: string;
    };
}

export interface DashboardProps {
    is_admin: boolean;
    menu: SidebarSection[];
    notif: AdminNotif;
    stats: DashboardStats;
    line: LineChartData;
    donut: DonutData;
    status_sistem: StatusItem[];
    feed: FeedTap[];
    pengajuan: PengajuanItem[];
    agenda_terdekat: AgendaTerdekat[];
    kalender_mini: MiniCal;
    aksi_cepat: QuickAction[];
    urls: { feed_semua: string; pengajuan_semua: string; data: string; kalender?: string; minical?: string };
}

export interface AgendaTerdekat {
    id: number;
    jenis: string;
    jenis_label: string;
    keterangan: string | null;
    rentang: string;
    hari_ini: boolean;
}

export interface MiniCal {
    bulan: string;
    bulan_label: string;
    offset: number;
    days: Array<{ d: number; tanggal: string; today: boolean; agenda: Array<{ jenis: string; keterangan: string | null }> }>;
}

/* ── Portal Dosen (React) ── */
export interface DosenAnalytics {
    period_label: string;
    mode: 'hari' | 'minggu' | 'bulan' | 'tahun';
    tanggal: string;
    kelas: string;
    tim: string;
    weekday: number | null;
    aktivitas: 'semua' | 'absensi';
    pbl_tim_keys: string[];
    stats: {
        total_mahasiswa: number;
        persen_hadir: number;
        persen_delta: number;
        persen_meta: string;
        tap_absensi: number;
        tap_prev: number;
        tap_delta: number;
        tap_meta: string;
    };
    line: { labels: string[]; values: number[]; absensi: number[]; subtitle?: string };
    donut: { labels: string[]; values: number[]; persen: number };
}

export interface DosenFeedItem {
    id: number;
    nama: string;
    nitk: string;
    tim: string;
    foto: string | null;
    jenis: string;
    sesi: string;
    waktu: string;
    menit_terlambat: number;
    pulang_lembur: boolean;
}

export interface DosenAnnouncement {
    id: number;
    judul: string;
    prioritas: string;
    isi: string;
    author: string;
    waktu: string;
}

export interface AgendaTerdekat {
    id: number;
    jenis: string;
    jenis_label: string;
    keterangan: string | null;
    rentang: string;
    hari_ini: boolean;
}

export interface DosenDashboardProps extends SharedProps {
    menu: SidebarSection[];
    notif: AdminNotif;
    analytics: DosenAnalytics;
    kelas_options: string[];
    tim_options: string[];
    pengumuman: DosenAnnouncement[];
    absensi_terbaru: DosenFeedItem[];
    kalender_mini: MiniCal;
    agenda_terdekat: AgendaTerdekat[];
    pbl: { has_pbl: boolean; tim_keys: string[] };
    urls: {
        data: string;
        minical: string;
        rekap: string;
        reports: string;
        mahasiswa: string;
        announcements: string;
        ruangan: string;
    };
}

export interface RekapRow {
    tanggal: string;
    nim: string;
    nama: string;
    kelas: string | null;
    prodi: string | null;
    semester: string | number | null;
    shift: string | null;
    sesi: string | null;
    status: string;
    slot_lab_masuk: string | null;
    jam_masuk: string | null;
    jam_pulang: string | null;
    menit_terlambat_masuk: number;
    menit_terlambat_pulang: number;
    pulang_lembur: boolean;
    harian_id?: number | null;
    koreksi_url?: string | null;
}

export interface RekapTapItem {
    urutan: number;
    nama: string;
    nim: string;
    kelas: string;
    tim: string;
    shift: string | null;
    sesi: string | null;
    slot_lab: string | null;
    menit_terlambat: number;
    waktu_tap: string;
    waktu_tap_label: string;
}

export interface RekapSnapshot {
    query: string;
    revision: string;
    tab: string;
    tab_counts: { all: number; hadir: number; alfa: number; tidak_lengkap: number };
    can_koreksi: boolean;
    kelas_scope_empty: boolean;
    kelas_scope_message: string | null;
    summary: {
        total: number; hadir: number; terlambat: number; lengkap: number;
        alfa: number; tidak_lengkap: number; masuk: number; belum: number;
        tidak_wajib: number; persentase_lengkap: number;
    };
    summary_text: { persen: string; 'persen-meta': string };
    chart: { labels: string[]; values: number[]; colors: string[]; center: number };
    pagination: { page: number; per_page: number; total: number; last_page: number };
    rows: RekapRow[];
    tap_masuk: RekapTapItem[];
}

export interface DosenAbsensiProps extends SharedProps {
    menu: SidebarSection[];
    notif: AdminNotif;
    initial: RekapSnapshot;
    filter: {
        q: string; kelas: string; tim: string; angkatan: string;
        program_studi_id: string; sesi: string; shift: string;
        semester: string; status: string; date: string;
    };
    options: {
        kelas: string[]; tim: string[]; angkatan: (string | number)[];
        semester: (string | number)[]; prodi: { id: number; nama: string }[];
        sesi: string[]; shift: string[];
    };
    meta: { kebijakan_label: string; hari_ini_wajib: boolean; tanggal: string };
    urls: { snapshot: string; export: string; print: string; reports: string };
}

export interface PeriodicStudent {
    user_id: number;
    nama: string;
    nim: string;
    kelas: string;
    tim: string;
    hadir: number;
    tidak_lengkap: number;
    alfa: number;
    terakhir: string | null;
}

export interface DosenReportsProps extends SharedProps {
    menu: SidebarSection[];
    notif: AdminNotif;
    scope_empty: boolean;
    scope_message: string | null;
    report: {
        filter: {
            periode: string; bulan: string; tanggal_mulai: string; tanggal_akhir: string;
            kelas: string; tim: string; aktivitas: string; rekor: string; rekor_group: string;
        };
        periode_label: string;
        activity_label: string;
        kebijakan_label: string;
        kelas_options: string[];
        tim_options: string[];
        summary: { mahasiswa: number; hadir: number; tidak_lengkap: number; alfa: number; mesin_online: number };
        chart: { labels: string[]; hadir: number[]; tidak_lengkap: number[]; alfa: number[] };
        machines: { aktivitas: string; id_mesin: string; total: number }[];
        recent: { waktu: string; nama: string; nim: string; kelas: string; tim: string; id_mesin: string; tap_jenis: string; status: string }[];
        students: PeriodicStudent[];
        rankings: { title: string; subtitle: string; metric: string; items: { rank: number; label: string; meta: string; value: number; value_label: string }[] };
    };
    urls: { index: string; excel: string; word: string; print: string };
}

export interface LaporanPeriodikProps extends SharedProps {
    menu: SidebarSection[];
    notif: AdminNotif;
    report: {
        filter: {
            periode: string; bulan: string; tanggal_mulai: string; tanggal_akhir: string;
            kelas: string; tim: string; aktivitas: string; rekor: string; rekor_group: string;
        };
        periode_label: string;
        activity_label: string;
        kelas_options: string[];
        tim_options: string[];
        summary: { mahasiswa: number; hadir: number; tidak_lengkap: number; alfa: number; mesin_online: number };
        chart: { labels: string[]; hadir: number[]; tidak_lengkap: number[]; alfa: number[] };
        machines: { aktivitas: string; id_mesin: string; total: number }[];
        students: PeriodicStudent[];
    };
    urls: { index: string; excel: string; word: string; print: string; rekap_harian: string };
}

export interface RekapHarianRow {
    nim: string;
    nama: string;
    kelas: string;
    tim: string;
    sesi: string;
    status: string;
    jam_masuk: string;
    jam_pulang: string;
    menit_terlambat_masuk: number;
    slot_lab_masuk: string | null;
}

export interface RekapHarianProps extends SharedProps {
    menu: SidebarSection[];
    notif: AdminNotif;
    initial: {
        summary: {
            total: number; hadir: number; terlambat: number; lengkap: number;
            alfa: number; tidak_lengkap: number; masuk: number; belum: number;
            tidak_wajib: number; persentase_lengkap: number;
        };
        rows: RekapHarianRow[];
        tap_masuk: { nama: string; nim: string; kelas: string; tim: string; slot_lab: string | null; waktu_tap: string; menit_terlambat: number }[];
        sesi_trend: { slot: string; total: number }[];
    };
    filter: { date: string; kelas: string; tim: string };
    options: { kelas: string[]; tim: string[] };
    meta: { tanggal: string; jadwal_label: string; kebijakan_label: string; hari_ini_wajib: boolean };
    urls: { index: string; live: string; export: string; print: string; reports: string };
}

export interface PblProjectCard {
    id: number;
    kode: string;
    nama: string;
    shift_sesi: string | null;
    aktif: boolean;
    foto_url: string | null;
    deskripsi: string | null;
    progress: number | null;
    status_label: string;
    is_mine: boolean;
    my_role: 'manpro' | 'co_manpro' | null;
    manpro: string | null;
    manpro_id: number | null;
    co_manpro_id: number | null;
    co_manpro: string | null;
    jumlah_anggota: number;
    ketua: string | null;
    wakil: string | null;
    members: { id: number; nama: string | null; nim: string | null; kelas: string | null; foto: string | null; role: string }[];
}

export interface DosenPblProps extends SharedProps {
    menu: SidebarSection[];
    notif: AdminNotif;
    stats: { total: number; mine: number; tim: number; mahasiswa: number };
    mine: PblProjectCard[];
    projects: PblProjectCard[];
    media_url: string;
    create_url: string;
    member_routes: { anggota: string; ketua: string; wakil: string; hapus: string; aktivitas: string };
    unassigned_mahasiswa: { id: number; nama: string; nim: string | null; kelas: string | null }[];
}

export interface DosenAnnouncementCard {
    id: number;
    judul: string;
    isi: string;
    isi_full: string;
    prioritas: string;
    target_label: string;
    status: string;
    tanggal: string | null;
    penulis: string | null;
}

export interface DosenPengumumanProps extends SharedProps {
    menu: SidebarSection[];
    notif: AdminNotif;
    stats: { total_mine: number; ke_tim: number; ke_mahasiswa: number; diterima: number };
    mine: DosenAnnouncementCard[];
    received: DosenAnnouncementCard[];
    tim_options: string[];
    store_url: string;
    batas: { harian: number; terpakai: number };
    aktivitas: { judul: string; tanggal: string | null; status: string }[];
}

export interface DosenSaranItem {
    id: number;
    tiket: string;
    subjek: string;
    pesan: string;
    status: string;
    status_label: string;
    tanggal: string | null;
    admin_reply: string | null;
    admin_replied_by: string | null;
    admin_replied_role: string | null;
    admin_replied_at: string | null;
    unread_reply: boolean;
}

export interface DosenSaranProps extends SharedProps {
    menu: SidebarSection[];
    notif: AdminNotif;
    items: DosenSaranItem[];
    stats: { semua: number; proses: number; ditanggapi: number; belum_dibaca: number };
    store_url: string;
    reply_url: string;
    read_url: string;
}

export interface AdminPblMember { id: number; nama: string | null; nim: string | null; kelas: string | null; foto: string | null; role: string }

export interface AdminPblProject {
    id: number;
    kode: string;
    nama: string;
    shift_sesi: string;
    aktif: boolean;
    foto_url: string | null;
    deskripsi: string | null;
    progress: number | null;
    status_label: string;
    tahun_ajaran: string | null;
    kategori: string | null;
    semester: string | null;
    periode_mulai: string | null;
    periode_selesai: string | null;
    manpro_id: number | null;
    manpro: string | null;
    co_manpro_id: number | null;
    co_manpro: string | null;
    jumlah_anggota: number;
    members: AdminPblMember[];
}

export interface AdminPblDosen { id: number; nama: string; nip: string | null; sebagai_manpro: number; sebagai_co: number }

export interface AdminPblProps extends SharedProps {
    menu: SidebarSection[];
    notif: AdminNotif;
    stats: { total: number; aktif: number; selesai: number; anggota: number };
    projects: AdminPblProject[];
    dosen_options: AdminPblDosen[];
    unassigned: { id: number; nama: string; nim: string | null; kelas: string | null }[];
    tahun_ajaran_options: string[];
    routes: {
        store: string; update: string; destroy: string; staff: string;
        ketua: string; wakil: string; clear_role: string; assign: string;
        clear_tim: string; import: string; csv_template: string; aktivitas: string; media: string;
    };
}

export interface DosenProfileProps extends SharedProps {
    menu: SidebarSection[];
    notif: AdminNotif;
    profile_user: {
        nama: string;
        nitk: string | null;
        email: string | null;
        email_verified: boolean;
        no_hp: string | null;
        role: string;
        foto: string | null;
        prodi: string | null;
        fakultas: string | null;
        approved_by: string | null;
        terdaftar: string | null;
    };
    pbl: { tim_keys: string[] };
    stats: { mahasiswa: number; persen_hadir: number; tim: number };
    aktivitas: { aksi: string; oleh: string; waktu: string | null; tanggal: string | null }[];
    urls: {
        profile_full: string;
        password_reset: string;
        photo_update: string;
        photo_delete: string;
    };
}

/* ── Manajemen Mesin ── */
export interface MesinItem {
    id: number;
    id_mesin: string;
    nama_mesin: string;
    tipe: string;
    tipe_label: string;
    ruangan: string | null;
    ruangan_id: number | null;
    is_active: boolean;
    /** Koneksi = heartbeat ≤ ambang; beda dari status administratif is_active */
    conn: 'online' | 'offline' | 'inactive';
    conn_label: string;
    last_seen_label: string;
    last_seen_exact: string | null;
    last_tap_label: string;
    last_tap_exact: string | null;
    updated_label: string | null;
}

export interface MesinStats {
    total: number;
    active: number;
    inactive: number;
    online: number;
    offline: number;
    by_type: Record<string, number>;
}

export interface MesinSetup {
    key: string;
    id: string;
    name: string;
    tipe: string;
    mode: 'create' | 'regenerate' | string;
}

export interface PaginationMeta {
    current_page: number;
    last_page: number;
    total: number;
    per_page: number;
    from: number | null;
    to: number | null;
}

export interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

export interface MesinProps {
    menu: SidebarSection[];
    notif: AdminNotif;
    stats: MesinStats;
    type_options: Record<string, string>;
    filters: { q: string; tipe: string; status: string };
    mesin: { data: MesinItem[]; meta: PaginationMeta; links: PaginationLink[] };
    can: { create: boolean; update: boolean; delete: boolean };
    ruangan_options: { id: number; nama: string; kode: string }[];
    setup: MesinSetup | null;
    threshold_minutes: number;
    api_base: string;
    urls: { index: string; store: string; monitoring: string; log: string };
}

/* ── Panduan / Program Mesin (Arduino IDE) ── */
export interface PanduanMesinRiwayat { label: string; hasil: string; waktu: string | null }
export interface PanduanMesinItem {
    id: number; id_mesin: string; nama_mesin: string; tipe: string; tipe_label: string;
    ruangan: string | null; is_active: boolean;
    conn: 'online' | 'offline' | 'inactive'; conn_label: string;
    last_seen_label: string; last_seen_exact: string | null; last_tap_label: string;
    sketch_url: string; conn_url: string; riwayat: PanduanMesinRiwayat[];
}
export interface PanduanMesinProps {
    menu: SidebarSection[];
    notif: AdminNotif;
    api_base: string;
    threshold_minutes: number;
    stats: { total: number; online: number; offline: number };
    mesin: PanduanMesinItem[];
    urls: { mesin: string; monitoring: string };
}

/* ── Monitoring Operasional ── */
export interface MonTypeCard {
    key: string; label: string; total: number;
    online: number; offline: number; inactive: number;
    rooms: number; is_vision: boolean;
}
export interface MonTab { key: string; label: string; count: number }
export interface MonUnit {
    key: string; kind: 'mesin' | 'ruangan';
    nama: string; sub: string | null; jenis: string; jenis_key: string;
    lokasi: string; status: 'online' | 'offline' | 'inactive' | 'tersedia';
    status_label: string; update_label: string; url: string;
}
export interface MonAlert { tone: string; title: string; desc: string; time: string }
export interface MonLiveTile { nama: string; ruangan: string | null; status: 'live' | 'offline' | 'inactive' }
export interface MonitorActivity {
    type: string; type_key: string; time: string | null; time_label: string; time_exact: string;
    student: string; identifier: string; machine: string; status: string; ok: boolean;
    profile_photo_url: string | null;
}
export interface MonMetrics {
    machine_total: number; machine_online: number; machine_offline: number; machine_inactive: number;
    latest_sync: string; latest_sync_human: string; absensi_today: number; events_today: number;
}
export interface MonitoringPayload {
    tab: string; tabs: MonTab[]; type_cards: MonTypeCard[]; has_vision: boolean;
    metrics: MonMetrics; units: MonUnit[]; alerts: MonAlert[]; live_tiles: MonLiveTile[];
    activities: MonitorActivity[];
    hourly: { labels: string[]; absensi: number[] };
    health: { labels: string[]; values: number[]; colors: string[] };
}
export interface MonitoringOperasionalProps extends MonitoringPayload {
    menu: SidebarSection[]; notif: AdminNotif; threshold_minutes: number;
    urls: { index: string; feed: string; mesin: string; log: string };
}

/* ── Pusat Kendali (khusus admin) ── */
export interface PusatKendaliKpi {
    online: number; mesin_online: number; mesin_total: number; mesin_down: number;
    pending_approval: number; pengajuan_menunggu: number; akses_ditolak_hari_ini: number;
}
export interface PusatKendaliOnlineUser {
    id: number; nama: string; role: string; foto: string | null; terakhir: string | null;
}
export interface PusatKendaliMesinDown {
    nama: string; tipe: string; ruangan: string | null; terakhir: string;
}
export interface PusatKendaliPendingUser {
    id: number; nama: string; role: string; sejak: string | null;
}
export interface PusatKendaliPengajuan { pemohon: string; ruangan: string; tanggal: string }
export interface PusatKendaliAksesDitolak {
    ruangan: string; nama: string; alasan: string; tipe: string; waktu: string | null;
}
export interface PusatKendaliPblLog {
    aktor: string; role: string; aksi: string; ringkasan: string | null; waktu: string | null;
}
export interface PusatKendaliPayload {
    kpi: PusatKendaliKpi;
    online_users: PusatKendaliOnlineUser[];
    mesin_down: PusatKendaliMesinDown[];
    pending_users: PusatKendaliPendingUser[];
    pengajuan_menunggu: PusatKendaliPengajuan[];
    akses_ditolak: PusatKendaliAksesDitolak[];
    pbl_terakhir: PusatKendaliPblLog[];
    generated_at: string;
}
export interface PusatKendaliProps extends PusatKendaliPayload {
    menu: SidebarSection[]; notif: AdminNotif;
    urls: {
        feed: string; users: string; mesin: string; monitoring: string;
        pengajuan: string; ruangan: string; pbl: string; notifikasi: string;
    };
}

/* ── Log Tap (Log Akses Operasional) ── */
export interface LogTapRow {
    id: number; waktu: string; waktu_sort: string | null;
    pengguna: { nama: string; email: string; identitas: string; role: string; foto: string | null };
    jenis: string; jenis_key: string;
    arah: 'masuk' | 'keluar' | null; arah_label: string;
    perangkat: { id_mesin: string; nama: string; ruangan: string | null };
    status: string; keterangan: string;
}
export interface LogTapStats {
    total: number; masuk: number; pulang: number; unik: number; perangkat: number;
    masuk_pct: number; pulang_pct: number; today: number; minggu_ini: number;
}
export interface LogTapProps {
    menu: SidebarSection[]; notif: AdminNotif;
    filters: { q: string; id_mesin: string; jenis: string; arah: string; tanggal_mulai: string; tanggal_akhir: string; per_page: number };
    options: { mesin: { id: string; nama: string }[]; jenis: { key: string; label: string }[] };
    stats: LogTapStats;
    rincian: { key: string; label: string; total: number; pct: number }[];
    tren: { labels: string[]; values: number[] };
    logs: {
        data: LogTapRow[];
        meta: { page: number; last_page: number; total: number; per_page: number; from: number; to: number };
    };
    urls: { index: string };
}

/* ── Atur Absensi ── */
export interface AturSesi {
    shift: string;
    jam_masuk: string;
    jam_mulai_tap_masuk: string;
    jam_pulang: string;
    toleransi_masuk_menit: number;
    toleransi_pulang_menit: number;
    grace_alfa_menit: number;
    aktif: boolean;
    timezone: string;
    updated_label: string | null;
}

export interface AturDayRule {
    aktif: boolean;
    jam_masuk: string;
    jam_pulang: string;
    jam_mulai_tap_masuk: string;
    keterangan: string;
}

export interface AturKalender {
    id: number;
    tanggal: string;
    tanggal_selesai: string | null;
    rentang: string;
    jenis: string;
    dampak: string;
    jam_khusus: string | null;
    keterangan: string;
    updated_label: string | null;
}

export interface AturOpsIssue { level: string; title: string; detail: string }

export interface AturOps {
    today: string;
    yesterday: string;
    kebijakan_label: string;
    hari_wajib_hari_ini: boolean;
    kamis_ready: boolean;
    heartbeat: {
        is_ok: boolean; at_label: string; at_exact: string | null;
        minutes_ago: number | null; command: string | null;
    };
    stats: {
        mahasiswa_aktif: number; tanpa_shift: number; taps_hari_ini: number;
        alfa_kemarin: number; belum_tap_hari_ini: number; lengkap_hari_ini: number;
    };
    close_hints: { shift: string; alfa_at: string; tidak_lengkap_at: string; timezone: string }[];
    issues: AturOpsIssue[];
    pbl: { ok: boolean; issues: AturOpsIssue[] };
}

export interface AturAbsensiProps {
    menu: SidebarSection[];
    notif: AdminNotif;
    kebijakan: {
        mode: string; mode_label: string; effective_from: string | null;
        jadwal_mingguan: Record<string, AturDayRule>;
    };
    hari_nama: Record<string, string>;
    sesi: AturSesi[];
    slot_lab: { no: number; mulai: string; selesai: string }[];
    kalender: AturKalender[];
    ringkasan: {
        hari_aktif: number; hari_label: string; slot_per_hari: number; slot_menit: number;
        sesi_rentang: string | null;
        kalender_aktif: { keterangan: string; rentang: string } | null;
        kalender_berikut: { keterangan: string; tanggal: string } | null;
        kalender_total: number;
    };
    riwayat: { label: string; by: string; time_label: string; time_exact: string }[];
    ops: AturOps;
    can: { update: boolean };
    tab: 'ringkasan' | 'jadwal' | 'kalender' | 'operasional';
    urls: {
        index: string; kebijakan: string; sesi: string; libur: string;
        tutup_sesi: string; generate_alfa: string; rekap: string;
        pbl: string; fingerprint: string; pengaturan: string;
    };
}

/* ── Fingerprint Enroll ── */
export interface FpStats {
    total: number; enrolled: number; pending_enroll: number; unassigned: number;
    capacity_used: number; capacity_total: number; completion_rate: number;
    role_label: string;
}

export interface FpCharts {
    donut: { labels: string[]; values: number[]; colors: string[]; center: number; center_sub: string };
    trend: { labels: string[]; values: number[] };
    kelas_bar: { labels: string[]; values: number[]; colors: string[]; subtitle?: string };
    sync_bar: { labels: string[]; values: number[]; colors: string[]; subtitle?: string };
}

export type FpRowStatus = 'enrolled' | 'legacy' | 'pending' | 'unassigned' | 'tidak_aktif';

export interface FpRow {
    id: number; nama: string; role: string; nim: string; email: string | null;
    kelas: string | null; tim: string | null; shift: 'pagi' | 'malam' | null;
    finger_id: number | null; is_active: boolean;
    status: FpRowStatus; kesiapan: string; can_clear: boolean;
    profile_url: string;
}

export interface FpSyncLogRow {
    id: number; waktu: string | null; waktu_human: string | null;
    id_mesin: string; action: string; delta: string | null;
    templates_count: number; finger_ids: string | null;
    ip: string | null; success: boolean; message: string | null;
}

export interface FingerprintProps {
    menu: SidebarSection[];
    notif: AdminNotif;
    stats: FpStats;
    charts: FpCharts;
    mahasiswa: {
        data: FpRow[];
        meta: { page: number; last_page: number; total: number; per_page: number; from: number; to: number };
    };
    filters: { q: string; status: string; sync_mesin: string; kelas: string; tim: string; role: string };
    options: { kelas: string[]; tim: string[]; mesin: { id_mesin: string; nama: string }[] };
    sync: {
        mode: string; burst_active: boolean; burst_until: string | null;
        stats: { total: number; today: number; pull_today: number; templates_today: number };
        log: { data: FpSyncLogRow[]; meta: { page: number; last_page: number; total: number } };
    };
    can: { update: boolean; delete: boolean };
    tab: 'ringkasan' | 'mahasiswa' | 'distribusi' | 'sinkronisasi' | 'log';
    urls: {
        index: string; user_base: string; backfill: string;
        sync_mode: string; sync_distribute: string; mesin: string;
    };
}

export interface UserRow {
    id: number;
    nama: string;
    email: string | null;
    no_hp: string | null;
    rfid_uid: string | null;
    has_akses_pin: boolean;
    nitk_nidn: string | null;
    role: 'admin' | 'laboran' | 'dosen' | 'mahasiswa' | string;
    role_label: string;
    avatar_url: string | null;
    kelas: string | null;
    tim: string | null;
    angkatan: number | string | null;
    semester: number | null;
    semester_type: 'ganjil' | 'genap' | null;
    program_studi_id: number | null;
    shift: 'pagi' | 'malam' | null;
    finger_id: number | null;
    is_active: boolean;
    approval_status: string;
    verify: 'menunggu_aktivasi' | 'approved' | 'rejected' | 'tunggu_admin' | 'tunggu_otp';
    enroll: 'enrolled' | 'legacy' | 'pending' | 'unassigned' | null;
    lock: 'admin' | 'temp' | null;
    suspicious: boolean;
    must_change_password: boolean;
    approval_note: string | null;
    kelas_cakupan: string[];
    can_issue_code: boolean;
    is_self: boolean;
    online: boolean | null;
    last_seen: string | null;
    profile_url: string;
}

export interface UserLogRow {
    waktu: string | null;
    waktu_human: string | null;
    subject: string;
    subject_email: string;
    subject_nitk: string;
    subject_role: string | null;
    action: string;
    label: string;
    tone: string;
    actor: string | null;
    actor_role: string | null;
    ip: string | null;
    note: string | null;
    meta_parts: string[];
}

export interface UserActivity {
    action: string;
    label: string;
    tone: string;
    subject: string;
    actor: string;
    waktu: string | null;
}

export interface UsersProps {
    menu: SidebarSection[];
    notif: AdminNotif;
    stats: {
        total: number; active: number;
        mahasiswa: number; dosen: number; laboran: number; admin: number;
        approved: number; pending_pipeline: number; locked: number;
        enrolled: number; enroll_pct: number; new_30d: number; online?: number;
    };
    role_chart: { labels: string[]; values: number[]; colors: string[] };
    users: {
        data: UserRow[];
        meta: { page: number; last_page: number; total: number; per_page: number; from: number; to: number };
    };
    filters: {
        q: string; role: string; status: string; approval_status: string;
        verify_status: string; login_lock: string; angkatan: string; semester: string; per_page: number;
    };
    options: {
        program_studi: { id: number; nama: string }[];
        pbl_tim: string[];
        angkatan: number[];
        kelas: string[];
    };
    alerts: {
        awaiting_activation: number; pending_otp: number;
        pending_approval: number; login_locked: number; onboarding: number;
    };
    aktivitas: UserActivity[];
    can: { create: boolean; update: boolean; delete: boolean };
    role_page: string | null;
    urls: {
        index: string; role_base: string; store: string;
        user_base: string; import: string; profiles_base: string; riwayat?: string;
    };
}

// ---------------- Manajemen Ruangan ----------------

export interface RgAturan {
    hari: number[];
    jam_buka: string;
    jam_tutup: string;
    min_advance_days: number;
    max_advance_days: number;
    max_duration_minutes: number;
    max_pending_per_team: number;
    max_peserta: number | null;
}

export interface RgRow {
    id: number;
    kode: string;
    nama: string;
    lokasi: string | null;
    kapasitas: number | null;
    fasilitas: string[];
    foto_url: string | null;
    aktif: boolean;
    aturan: RgAturan;
    perangkat_total: number;
    jadwal_label: string;
    last_event: { jenis: string; label: string | null } | null;
}

export interface RgMesin {
    id: number;
    id_mesin: string;
    nama: string;
    tipe: string;
    is_active: boolean;
    ruangan_id: number | null;
}

export interface RuanganProps {
    menu: SidebarSection[];
    notif: AdminNotif;
    filters: { q: string; status: string; per_page: number };
    stats: { total: number; aktif: number; nonaktif: number; terikat: number; terikat_pct: number };
    ruangan: {
        data: RgRow[];
        meta: { page: number; last_page: number; total: number; per_page: number; from: number; to: number };
    };
    mesin_list: RgMesin[];
    ruangan_options: { id: number; nama: string; kode: string }[];
    can: { create: boolean; update: boolean };
    is_admin: boolean;
    urls: { index: string; store: string; update_base: string; bind_base: string };
}

// ---------------- Pengajuan Ruangan (laboran) ----------------

export interface PgjRow {
    id: number;
    kode: string;
    judul: string;
    status: string;
    status_efektif: string;
    boleh_putus: boolean;
    pemohon: { nama: string; role: string; tim: string | null };
    pembimbing: string | null;
    ruangan: string;
    tanggal: string;
    tanggal_iso: string;
    hari: string;
    jam: string;
    peserta_n: number;
    peserta: string[];
    pengganti: { mulai: string; selesai: string | null; catatan: string | null } | null;
    alasan: string | null;
    diputus_oleh: string | null;
    diputus_pada: string | null;
    dibuat: string | null;
}

export interface PgjRiwayat {
    waktu: string; kode: string; kegiatan: string; ruangan: string;
    pemohon: string; keputusan: string; oleh: string;
}

export interface PengajuanRuanganProps {
    menu: SidebarSection[];
    notif: AdminNotif;
    filters: { status: string; q: string; per_page: number };
    stats: {
        total: number; menunggu: number; disetujui: number; ditolak: number;
        berlangsung: number; menunggu_7d: number; disetujui_pct: number; ditolak_pct: number;
    };
    pengajuan: {
        data: PgjRow[];
        meta: { page: number; last_page: number; total: number; per_page: number; from: number; to: number };
    };
    riwayat: PgjRiwayat[];
    can: { update: boolean };
    urls: {
        index: string; setujui_base: string; tolak_base: string;
        pengganti_base: string; cabut_base: string;
    };
}

// ---------------- Petugas Ruangan ----------------

export interface PtgJadwal {
    ruangan_id: number | null;
    ruangan: string;
    hari: number[];
    hari_label: string;
    jam_mulai: string | null;
    jam_selesai: string | null;
    jam_label: string;
}

export interface PtgRow {
    id: number;
    nama: string;
    id_internal: string;
    tipe_kredensial: string;
    kredensial_label: string;
    status: string;
    berlaku_mulai: string | null;
    berlaku_selesai: string | null;
    jadwal: PtgJadwal[];
    ruangan_akses: string[];
}

export interface PtgAkses {
    petugas: string; id_internal: string; status: string;
    ruangan: string; hari_label: string; jam_label: string;
}

export interface PtgRiwayat {
    waktu: string; nama: string; jenis: string; jenis_label: string;
    hasil: string | null; ruangan: string; keterangan: string;
}

export interface PetugasProps {
    menu: SidebarSection[];
    notif: AdminNotif;
    filters: { q: string };
    tab: 'daftar' | 'akses' | 'riwayat';
    stats: { total: number; aktif: number; nonaktif: number; akses_bulan_ini: number };
    petugas: PtgRow[];
    akses: PtgAkses[];
    riwayat: PtgRiwayat[];
    ruangan_options: { id: number; nama: string; kode: string }[];
    can: { create: boolean; update: boolean };
    urls: { index: string; store: string; update_base: string };
}

// ---------------- Pengumuman ----------------

export interface AnnRow {
    id: number;
    judul: string;
    isi: string;
    preview: string;
    target: string;
    target_label: string;
    prioritas: string;
    status: 'aktif' | 'draft' | 'kadaluarsa';
    author: string;
    created: string | null;
    expires: string | null;
    expires_input: string | null;
}

export interface AnnArsip {
    id: number; judul: string; target_label: string;
    status: 'draft' | 'kadaluarsa';
    created: string | null; expires: string | null; author: string;
}

export interface PengumumanProps {
    menu: SidebarSection[];
    notif: AdminNotif;
    filters: { q: string; target: string; prioritas: string; status: string; from: string; to: string; per_page: number };
    stats: {
        total: number; aktif: number; draft: number; kadaluarsa: number;
        aktif_pct: number; kadaluarsa_pct: number;
        bulan_ini: number; delta_vs_bulan_lalu: number;
    };
    items: {
        data: AnnRow[];
        meta: { page: number; last_page: number; total: number; per_page: number; from: number; to: number };
    };
    arsip: AnnArsip[];
    can: { create: boolean; update: boolean; delete: boolean };
    urls: { index: string; store: string; base: string };
}

// ---------------- Saran & Masukan ----------------

export interface SugRow {
    id: number;
    kode: string;
    pelapor: string;
    pelapor_role: string;
    avatar: string | null;
    judul: string;
    ringkasan: string;
    kategori: string;
    tanggal: string | null;
    status: string;
    baru: boolean;
}

export interface SugSelected {
    id: number;
    kode: string;
    pelapor: string;
    pelapor_role: string;
    pelapor_email: string | null;
    avatar: string | null;
    tanggal: string | null;
    status: string;
    judul: string;
    isi: string;
    lampiran_url: string | null;
    balasan: string | null;
    dibalas_oleh: string | null;
    dibalas_pada: string | null;
    user_baca_balasan: boolean;
}

export interface SaranMasukanProps {
    menu: SidebarSection[];
    notif: AdminNotif;
    filters: { q: string; status: string; source: string; from: string; to: string; per_page: number };
    stats: {
        total: number; baru: number; diproses: number; ditanggapi: number;
        bulan_ini: number; delta_vs_bulan_lalu: number;
    };
    items: {
        data: SugRow[];
        meta: { page: number; last_page: number; total: number; per_page: number; from: number; to: number };
    };
    selected: SugSelected | null;
    analisis: {
        per_sumber: { label: string; n: number }[];
        per_status: { label: string; n: number }[];
        tren: { labels: string[]; values: number[] };
        respon_jam: number | null;
    };
    can: { update: boolean; delete: boolean };
    urls: { index: string; base: string };
}

/* ── Antrian Notifikasi ── */
export interface NotifQueueItem {
    kode: string;
    ops_key: string;
    fingerprint: string;
    judul: string;
    isi: string;
    target: string;
    prioritas: 'Urgent' | 'Tinggi' | 'Normal';
    tone: 'danger' | 'warning' | 'info';
    status: 'Menunggu';
    peminta: string;
    waktu: string;
    jumlah: number;
    url: string | null;
    can_dismiss: boolean;
}

export interface NotifActivityRow {
    id: number;
    waktu: string | null;
    subjek: string;
    subjek_meta: string;
    aksi: string;
    tone: string;
    oleh: string;
    catatan: string | null;
}

export interface AntrianNotifikasiProps extends SharedProps {
    menu: SidebarSection[];
    notif: AdminNotif;
    kpi: { total: number; menunggu: number; diproses: number; terjadwal: number };
    items: NotifQueueItem[];
    activity: {
        data: NotifActivityRow[];
        meta: { page: number; last_page: number; total: number; from: number; to: number };
    };
    urls: { dismiss: string; riwayat_hapus: string };
}

/* ── Pengaturan Sistem ── */
export interface SettingsInfo {
    nama_sistem: string;
    nama_laboratorium: string;
    deskripsi: string;
    versi: string;
    lingkungan: string;
    is_production: boolean;
    diperbarui: string;
    status: string;
}

export interface SettingsGeneral {
    nama_sistem: string;
    nama_laboratorium: string;
    zona_waktu: string;
    bahasa: string;
    format_tanggal: string;
    format_waktu: string;
}

export interface SettingsSecurityRow { label: string; value: string; status: string }
export interface SettingsIntegrationRow { label: string; detail: string; configured: boolean }
export interface LiburRow { id: number; tanggal: string; tanggal_label: string; keterangan: string | null; url: string }
export interface ProdiRow { id: number; kode: string; nama: string }

export interface PengaturanProps extends SharedProps {
    menu: SidebarSection[];
    notif: AdminNotif;
    info: SettingsInfo;
    general: SettingsGeneral;
    page_branding: { logo_url: string; has_custom: boolean; default_url: string };
    security: SettingsSecurityRow[];
    integrations: SettingsIntegrationRow[];
    libur: LiburRow[];
    prodi: ProdiRow[];
    feature_badges: {
        enabled: boolean; until: string;
        items: { key: string; label: string; state: string; saved: string }[];
    };
    urls: {
        update: string; branding: string; branding_reset: string; feature_badges: string;
        libur: string; prodi: string; absensi: string;
    };
}

/* ── Kalender & Agenda ── */
export interface KalAgendaItem {
    id: number;
    jenis: 'libur' | 'peringatan' | 'agenda' | 'ujian' | 'perubahan_jam' | 'informasi';
    jenis_label: string;
    dampak: string;
    dampak_label: string;
    prioritas: 'rendah' | 'normal' | 'penting' | 'mendesak';
    prioritas_label: string;
    keterangan: string | null;
    tanggal: string;
    tanggal_selesai: string | null;
    rentang: string;
    tanggal_label: string;
    jam_khusus: string | null;
}

export interface KalDay {
    tanggal: string;
    day: number;
    dow: number;
    is_today: boolean;
    is_wajib: boolean;
    jam: string | null;
    agenda: KalAgendaItem[];
}

export interface KalenderAgendaProps extends SharedProps {
    menu: SidebarSection[];
    notif: AdminNotif;
    month: { value: string; label: string; prev: string; next: string; is_current: boolean };
    first_offset: number;
    days: KalDay[];
    agenda: KalAgendaItem[];
    kpi: { agenda: number; hari_aktif: number; hari_label: string; libur: number; peringatan: number };
    jadwal: { hari_label: string; jam: string | null; toleransi: number | null };
    can_update: boolean;
    urls: { index: string; store: string; update_base: string; destroy_base: string; atur_absensi: string };
}

export interface AdminImportRow {
    line: number;
    identifier: string;
    nama: string;
    status: 'siap' | 'dibuat' | 'duplikat' | 'gagal' | 'dilewati' | string;
    reason: string;
}

export interface AdminImportProps extends SharedProps {
    menu: SidebarSection[];
    notif: AdminNotif;
    mode: 'form' | 'result';
    types: Record<string, { label: string; allowed: boolean; columns: string[] }>;
    selected: string;
    ttl_days: number;
    urls: {
        preview: string;
        index: string;
        users: string;
        templates: Record<string, { xlsx: string; csv: string }>;
    };
    payload?: {
        token: string;
        type: string;
        file_name: string;
        created_at: string | null;
        applied_at: string | null;
        state: 'preview' | 'applied';
        notices: string[];
        totals: { total: number; siap: number; dibuat: number; duplikat: number; gagal: number; dilewati: number };
        rows: AdminImportRow[];
    };
    labels?: Record<string, string>;
    id_label?: string;
    type_label?: string;
    can_apply?: boolean;
    cards_available?: boolean;
    result_urls?: { apply: string; report: string; cards: string };
}

/* ── Pelatihan AI ── */
export interface AiTrainingProps {
    menu: SidebarSection[];
    notif: AdminNotif;
    unmatched: { q: string; n: number; terakhir: string }[];
    feedback: {
        ringkasan: { bad: number; ok: number; great: number };
        per_topik: { topik: string; bad: number; ok: number; great: number }[];
        bad_terbaru: { waktu: string; q: string; a: string }[];
        respons: { waktu: string; q: string; a: string; topik: string; level: 'bad' | 'ok' | 'great' }[];
    };
    knowledge: string;
    can_edit: boolean;
    urls: { index: string; save_knowledge: string; delete_respons: string };
}
