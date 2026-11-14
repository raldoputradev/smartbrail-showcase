@extends('layouts.dashboard')

@section('title', 'Proyek PBL')
@section('page-title', 'Proyek PBL')
@section('page-subtitle', 'Kelola proyek, manpro, dan penempatan mahasiswa')

@section('sidebar-nav')
    @include('admin._sidebar')
@endsection

@section('styles')
    <link rel="stylesheet" href="{{ asset('css/pbl-modal-premium.css') }}?v=7">
    <link rel="stylesheet" href="{{ asset('css/pbl-admin-premium.css') }}?v=2">
@endsection

@section('content')
<div class="pbl-admin-page">
    @if(session('success'))
        <div class="card animate-in" style="margin-bottom:16px;padding:14px;background:rgba(15,118,110,0.1);">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="card animate-in" style="margin-bottom:16px;padding:14px;background:rgba(220,38,38,0.1);">{{ session('error') }}</div>
    @endif
    @if(session('csv_errors'))
        <div class="card animate-in" style="margin-bottom:16px;padding:14px;background:rgba(245,158,11,0.12);font-size:13px;">
            <strong style="display:block;margin-bottom:6px;">Beberapa baris CSV gagal:</strong>
            <ul style="margin:0;padding-left:18px;max-height:160px;overflow-y:auto;">
                @foreach(session('csv_errors') as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif
    @if(isset($errors) && $errors->any())
        <div class="card animate-in" style="margin-bottom:16px;padding:14px;background:rgba(220,38,38,0.1);font-size:13px;">
            <ul style="margin:0;padding-left:18px;">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="stat-cards pbl-admin-stats animate-in">
        @include('partials.stat-card', ['tone' => 'teal', 'icon' => 'folders', 'label' => 'Total PBL'.(!empty($statsFiltered) ? ' (filter)' : ''), 'value' => $stats['total'], 'meta' => 'Proyek pada daftar aktif'])
        @include('partials.stat-card', ['tone' => 'blue', 'icon' => 'circle-check', 'label' => 'Aktif', 'value' => $stats['aktif'], 'meta' => 'Siap dipakai absensi'])
        @include('partials.stat-card', ['tone' => 'amber', 'icon' => 'sun', 'label' => 'Sesi pagi', 'value' => $stats['pagi'], 'meta' => 'Shift pagi'])
        @include('partials.stat-card', ['tone' => 'violet', 'icon' => 'moon', 'label' => 'Sesi malam', 'value' => $stats['malam'], 'meta' => 'Shift malam'])
    </div>

    @php $pblReady = $pblReadiness ?? ['ok' => true, 'issues' => [], 'stats' => []]; @endphp
    <div class="card animate-in pbl-ready-card {{ ($pblReady['ok'] ?? false) ? 'is-ok' : 'is-bad' }}">
        <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
            <h3 style="margin:0;">Checklist data PBL (absensi Kamis)</h3>
            <span style="font-size:12px;font-weight:800;padding:6px 12px;border-radius:999px;background:{{ ($pblReady['ok'] ?? false) ? 'rgba(15,118,110,0.15)' : 'rgba(220,38,38,0.12)' }};color:{{ ($pblReady['ok'] ?? false) ? '#0f766e' : '#b91c1c' }};">
                {{ ($pblReady['ok'] ?? false) ? 'Siap' : 'Perlu perbaikan' }}
            </span>
        </div>
        <div class="card-body" style="font-size:13px;">
            @if(!empty($pblReady['stats']))
                <p style="color:var(--text-muted);margin:0 0 8px;">
                    Mhs aktif: <strong>{{ $pblReady['stats']['mahasiswa_aktif'] ?? 0 }}</strong> ·
                    Proyek aktif: <strong>{{ $pblReady['stats']['proyek_aktif'] ?? 0 }}</strong> ·
                    Tanpa tim: <strong>{{ $pblReady['stats']['tanpa_tim'] ?? 0 }}</strong> ·
                    Tanpa shift: <strong>{{ $pblReady['stats']['tanpa_shift'] ?? 0 }}</strong>
                </p>
            @endif
            @if(!empty($pblReady['issues']))
                <ul style="margin:0;padding-left:18px;line-height:1.6;">
                    @foreach($pblReady['issues'] as $issue)
                        <li style="color:{{ ($issue['level'] ?? '') === 'danger' ? '#b91c1c' : '#b45309' }};">
                            <strong>{{ $issue['title'] }}</strong>
                            @if(($issue['count'] ?? 0) > 0) ({{ $issue['count'] }}) @endif — {{ $issue['detail'] }}
                        </li>
                    @endforeach
                </ul>
            @else
                <p style="margin:0;color:#0f766e;">Semua checklist PBL terpenuhi.</p>
            @endif
        </div>
    </div>

    <div class="card animate-in pbl-import-hub" id="import-csv" style="scroll-margin-top:88px;">
        <div class="pbl-import-hub__head">
            <div>
                <h3>Pusat data massal</h3>
                <p>Import proyek atau sinkron penempatan mahasiswa lewat CSV. Unduh template, isi di Excel/Sheets, lalu jatuhkan file ke zona unggah.</p>
            </div>
            <span class="pbl-import-hub__tag">@include('components.icon', ['name' => 'sparkles']) CSV · batch aman</span>
        </div>
        <div class="pbl-import-grid">
            <div class="pbl-import-card pbl-import-card--projects">
                <div class="pbl-import-card__top">
                    <span class="pbl-import-card__icon">@include('components.icon', ['name' => 'folder-plus'])</span>
                    <div>
                        <h4 class="pbl-import-card__title">Import Proyek PBL</h4>
                        <p class="pbl-import-card__desc">Buat / perbarui daftar proyek beserta manpro &amp; co-manpro.</p>
                    </div>
                </div>
                <ul class="pbl-import-steps">
                    <li><b>1</b> Unduh template</li>
                    <li><b>2</b> Isi baris proyek</li>
                    <li><b>3</b> Unggah &amp; impor</li>
                </ul>
                <p class="pbl-import-cols">Kolom: <code>kode, nama, shift_sesi, manpro, co_manpro</code> — shift <code>pagi</code>/<code>malam</code>, nama dosen harus cocok akun SIMALAS.</p>
                <form method="POST" action="{{ route('admin.pbl.import-projects') }}" enctype="multipart/form-data" class="pbl-import-form" data-pbl-import="projects">
                    @csrf
                    <div class="pbl-dropzone" data-pbl-dropzone>
                        <input class="pbl-dropzone__input" type="file" name="csv_projects" accept=".csv,.txt,text/csv" required data-pbl-file>
                        <div class="pbl-dropzone__idle">
                            <strong>Seret file CSV ke sini</strong>
                            <span>atau klik untuk memilih dari perangkat</span>
                            <em>Browse file</em>
                        </div>
                        <div class="pbl-dropzone__ready">
                            <span class="pbl-dropzone__file" data-pbl-filename>—</span>
                            <button type="button" class="pbl-dropzone__clear" data-pbl-clear>Ganti</button>
                        </div>
                    </div>
                    <div class="pbl-import-actions">
                        <button type="submit" class="btn-premium btn-premium--primary">Import Proyek</button>
                        <a href="{{ route('admin.pbl.csv-template', ['type' => 'projects']) }}" class="pbl-template-link">
                            @include('components.icon', ['name' => 'download']) Template CSV
                        </a>
                    </div>
                </form>
            </div>

            <div class="pbl-import-card pbl-import-card--sync">
                <div class="pbl-import-card__top">
                    <span class="pbl-import-card__icon">@include('components.icon', ['name' => 'users-round'])</span>
                    <div>
                        <h4 class="pbl-import-card__title">Sinkron Tim Mahasiswa</h4>
                        <p class="pbl-import-card__desc">Tempatkan mahasiswa ke tim PBL secara massal + notifikasi in-app.</p>
                    </div>
                </div>
                <ul class="pbl-import-steps">
                    <li><b>1</b> Unduh template</li>
                    <li><b>2</b> Isi NIM &amp; tim</li>
                    <li><b>3</b> Sinkronkan</li>
                </ul>
                <p class="pbl-import-cols">Kolom: <code>nim, tim, shift</code> — <code>tim</code> = nama atau kode proyek PBL yang sudah ada.</p>
                <form method="POST" action="{{ route('admin.pbl.sync-mahasiswa-tim') }}" enctype="multipart/form-data" class="pbl-import-form" data-pbl-import="sync">
                    @csrf
                    <div class="pbl-dropzone" data-pbl-dropzone>
                        <input class="pbl-dropzone__input" type="file" name="csv_mahasiswa_tim" accept=".csv,.txt,text/csv" required data-pbl-file>
                        <div class="pbl-dropzone__idle">
                            <strong>Seret file CSV ke sini</strong>
                            <span>atau klik untuk memilih dari perangkat</span>
                            <em>Browse file</em>
                        </div>
                        <div class="pbl-dropzone__ready">
                            <span class="pbl-dropzone__file" data-pbl-filename>—</span>
                            <button type="button" class="pbl-dropzone__clear" data-pbl-clear>Ganti</button>
                        </div>
                    </div>
                    <div class="pbl-import-actions">
                        <button type="submit" class="btn-premium btn-premium--primary">Sinkron Tim</button>
                        <a href="{{ route('admin.pbl.csv-template', ['type' => 'mahasiswa_tim']) }}" class="pbl-template-link">
                            @include('components.icon', ['name' => 'download']) Template CSV
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="card animate-in" id="daftar-proyek" style="margin-bottom:20px;scroll-margin-top:88px;">
        <div class="card-header pbl-list-head">
            <div>
                <h3>Daftar Proyek</h3>
                <p>{{ $stats['total'] }} PBL ditampilkan · ketik untuk cari instan · filter sesi di kanan</p>
            </div>
            <button type="button" class="pbl-btn-save" style="width:auto;padding:12px 20px;" onclick="openPblCreate()">
                @include('components.icon', ['name' => 'plus'])<span>Tambah PBL</span>
            </button>
        </div>

        <form method="GET" action="{{ route('admin.pbl.index') }}#daftar-proyek" id="pblProjectFilterForm">
            @foreach(['mhs_q', 'mhs_filter', 'mhs_tim'] as $mhsField)
                @if(request($mhsField))
                    <input type="hidden" name="{{ $mhsField }}" value="{{ request($mhsField) }}">
                @endif
            @endforeach
            @if(request('mhs_page'))<input type="hidden" name="mhs_page" value="{{ request('mhs_page') }}">@endif

            <div class="pbl-search-bar">
                <div class="pbl-search-wrap">
                    <div class="pbl-search-field {{ request('q') ? 'has-value' : '' }}" id="pblProjectSearchField">
                        @include('components.icon', ['name' => 'search'])
                        <input
                            type="search"
                            name="q"
                            id="pblProjectSearch"
                            class="pbl-search-input"
                            value="{{ request('q') }}"
                            placeholder="Cari kode, nama PBL, atau manpro…"
                            autocomplete="off"
                            enterkeyhint="search"
                            aria-autocomplete="list"
                            aria-controls="pblProjectSearchDropdown"
                            role="combobox"
                            aria-expanded="false"
                        >
                        <button type="button" class="pbl-search-clear" id="pblProjectSearchClear" title="Hapus pencarian" aria-label="Hapus pencarian">
                            @include('components.icon', ['name' => 'x'])
                        </button>
                    </div>
                    <div class="pbl-search-dropdown" id="pblProjectSearchDropdown" role="listbox" aria-label="Hasil pencarian proyek"></div>
                </div>

                <div class="pbl-session-pills" role="group" aria-label="Filter sesi">
                    @php $shiftNow = request('shift', ''); @endphp
                    <button type="button" class="pbl-session-pill {{ $shiftNow === '' ? 'is-active' : '' }}" data-pbl-shift="">Semua</button>
                    <button type="button" class="pbl-session-pill {{ $shiftNow === 'pagi' ? 'is-active' : '' }}" data-pbl-shift="pagi">Pagi</button>
                    <button type="button" class="pbl-session-pill {{ $shiftNow === 'malam' ? 'is-active' : '' }}" data-pbl-shift="malam">Malam</button>
                </div>
                <input type="hidden" name="shift" id="pblProjectShift" value="{{ $shiftNow }}">

                <div class="pbl-search-meta" id="pblProjectSearchMeta">
                    <span>
                        @if(request('q') || request('shift'))
                            Hasil filter: <strong>{{ $stats['total'] }}</strong> proyek
                            @if(request('q')) · kata kunci “{{ request('q') }}” @endif
                            @if(request('shift')) · sesi {{ request('shift') }} @endif
                        @else
                            Menampilkan semua proyek · ketik untuk lihat saran dropdown (tanpa refresh)
                        @endif
                    </span>
                    <a href="{{ route('admin.pbl.index', array_filter(request()->only(['mhs_q', 'mhs_filter', 'mhs_tim']))) }}#daftar-proyek" id="pblProjectResetLink" style="color:#5eead4;font-weight:700;text-decoration:none;{{ (request('q') || request('shift')) ? '' : 'display:none;' }}">Reset filter</a>
                </div>
            </div>
        </form>

        <div class="card-body" style="padding:0;overflow-x:auto;">
            <table class="data-table" style="min-width:900px;">
                <thead>
                    <tr>
                        <th>Kode</th>
                        <th>Nama PBL</th>
                        <th>Manpro</th>
                        <th>Co-Manpro</th>
                        <th>Sesi</th>
                        <th>Mhs</th>
                        <th>Status</th>
                        <th style="text-align:right;">Aksi</th>
                    </tr>
                </thead>
                <tbody id="pblProjectTableBody">
                    @forelse($projects as $p)
                        <tr>
                            <td style="font-weight:700;font-family:monospace;font-size:12px;">{{ $p->kode }}</td>
                            <td style="font-weight:600;">{{ $p->nama }}</td>
                            <td>{{ $p->manproUser?->nama_lengkap ?? $p->manpro ?? '—' }}</td>
                            <td>{{ $p->coManproUser?->nama_lengkap ?? $p->co_manpro ?? '—' }}</td>
                            <td><span class="status-badge" style="background:rgba(74,144,217,0.12);color:#2563eb;">{{ ucfirst($p->shift_sesi) }}</span></td>
                            <td>{{ $p->jumlah_mhs ?? 0 }}</td>
                            <td>
                                @if($p->aktif)
                                    <span class="status-badge success">Aktif</span>
                                @else
                                    <span class="status-badge warning">Nonaktif</span>
                                @endif
                            </td>
                            <td style="text-align:right;white-space:nowrap;">
                                <button type="button" class="pbl-action-icon pbl-edit-btn" title="Edit"
                                    data-id="{{ $p->id }}"
                                    data-kode="{{ e($p->kode) }}"
                                    data-nama="{{ e($p->nama) }}"
                                    data-manpro="{{ e($p->manpro ?? '') }}"
                                    data-co-manpro="{{ e($p->co_manpro ?? '') }}"
                                    data-manpro-user-id="{{ $p->manpro_user_id ?? '' }}"
                                    data-co-manpro-user-id="{{ $p->co_manpro_user_id ?? '' }}"
                                    data-shift="{{ $p->shift_sesi }}"
                                    data-aktif="{{ $p->aktif ? '1' : '0' }}">@include('components.icon', ['name' => 'pencil'])</button>
                                <form method="POST" action="{{ route('admin.pbl.destroy', $p) }}" class="inline-action-form"
                                    data-swal-confirm="Hapus proyek {{ $p->kode }}? Mahasiswa di tim ini akan kehilangan penempatan."
                                    data-swal-confirm-button="Ya, hapus" data-swal-confirm-color="#f43f5e"
                                    style="display:inline;">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="pbl-action-icon pbl-action-icon--danger" title="Hapus">@include('components.icon', ['name' => 'trash-2'])</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" style="text-align:center;padding:28px;color:var(--text-muted);">Tidak ada proyek pada filter ini. Coba kosongkan pencarian atau ganti sesi.</td></tr>
                    @endforelse
                </tbody>
            </table>
            <div style="padding:12px 16px;" id="pblProjectPagination">{{ $projects->links() }}</div>
        </div>
    </div>

    <div class="card animate-in" id="penempatan-mhs" style="scroll-margin-top: 88px;">
        <div class="card-header" style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:10px;">
            <div>
                <h3 style="margin:0;">Penempatan Mahasiswa ke Tim PBL</h3>
                <span style="font-size:12px;color:var(--text-muted);">Klik kartu tim untuk lihat detail &amp; ubah penempatan</span>
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap;font-size:12px;">
                <span class="status-badge" style="background:rgba(148,163,184,0.15);">Total {{ $mhsStats['total'] }}</span>
                <span class="status-badge warning">Belum tim {{ $mhsStats['belum'] }}</span>
                <span class="status-badge success">Sudah tim {{ $mhsStats['sudah'] }}</span>
            </div>
        </div>
        <form method="GET" action="{{ route('admin.pbl.index') }}#penempatan-mhs" id="pblMhsFilterForm">
            @if(request('q'))<input type="hidden" name="q" value="{{ request('q') }}">@endif
            @if(request('shift'))<input type="hidden" name="shift" value="{{ request('shift') }}">@endif
            @if(request('page'))<input type="hidden" name="page" value="{{ request('page') }}">@endif
            <div class="pbl-search-bar">
                <div class="pbl-search-field {{ $mhsQ ? 'has-value' : '' }}" id="pblMhsSearchField">
                    @include('components.icon', ['name' => 'search'])
                    <input
                        type="search"
                        name="mhs_q"
                        id="pblMhsSearch"
                        class="pbl-search-input"
                        value="{{ $mhsQ }}"
                        placeholder="Cari NIM, nama, kelas, atau semester…"
                        autocomplete="off"
                        enterkeyhint="search"
                    >
                    <button type="button" class="pbl-search-clear" id="pblMhsSearchClear" title="Hapus pencarian" aria-label="Hapus pencarian">
                        @include('components.icon', ['name' => 'x'])
                    </button>
                </div>
                <div class="pbl-mhs-filters">
                    <select name="mhs_filter" id="pblMhsFilterStatus">
                        <option value="semua" @selected($mhsFilter === 'semua')>Semua mahasiswa</option>
                        <option value="belum" @selected($mhsFilter === 'belum')>Belum punya tim</option>
                        <option value="sudah" @selected($mhsFilter === 'sudah')>Sudah punya tim</option>
                    </select>
                    <select name="mhs_tim" id="pblMhsFilterTim">
                        <option value="">Semua tim</option>
                        @foreach($timOptions as $timOpt)
                            <option value="{{ $timOpt }}" @selected($mhsTim === $timOpt)>{{ $timOpt }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="pbl-search-meta">
                    <span>Filter penempatan · ubah dropdown untuk terapkan langsung</span>
                    @if($mhsQ || $mhsFilter !== 'semua' || $mhsTim)
                        <a href="{{ route('admin.pbl.index', array_filter(['q' => request('q'), 'shift' => request('shift')])) }}#penempatan-mhs" style="color:#5eead4;font-weight:700;text-decoration:none;">Reset</a>
                    @endif
                </div>
            </div>
        </form>

        @include('admin._pbl_placement_cards')
    </div>
</div>

    <div class="pbl-modal-backdrop" id="pblCreateModal" aria-hidden="true">
        <div class="pbl-modal" role="dialog" aria-labelledby="pblCreateTitle">
            <button type="button" class="pbl-modal-close" onclick="closePblCreate()" aria-label="Tutup">
                @include('components.icon', ['name' => 'x'])
            </button>
            <div class="pbl-modal-badge">Baru</div>
            <h3 id="pblCreateTitle">Tambah PBL</h3>
            <p class="pbl-modal-sub">Isi data proyek, lalu pilih manpro &amp; co-manpro dari dropdown dosen terdaftar.</p>
            <form method="POST" action="{{ route('admin.pbl.store') }}" id="pblCreateForm" class="pbl-form-grid">
                @csrf
                <input type="hidden" name="_pbl_form" value="create">
                <div>
                    <label class="pbl-field-label">Kode EL *</label>
                    <input type="text" name="kode" id="create_kode" value="{{ old('kode') }}" required placeholder="PBL-RE-044" maxlength="40">
                </div>
                <div>
                    <label class="pbl-field-label">Nama PBL *</label>
                    <input type="text" name="nama" id="create_nama" value="{{ old('nama') }}" required placeholder="AIoT (Si Malas)" maxlength="200">
                </div>
                @include('admin._pbl_dosen_picker', ['prefix' => 'create', 'dropdown' => true])
                <div class="pbl-form-row-2">
                    <div>
                        <label class="pbl-field-label">Sesi *</label>
                        <select name="shift_sesi" id="create_shift" required>
                            <option value="pagi" @selected(old('shift_sesi', 'pagi') === 'pagi')>Pagi</option>
                            <option value="malam" @selected(old('shift_sesi') === 'malam')>Malam</option>
                        </select>
                    </div>
                    <div style="display:flex;align-items:center;padding-top:28px;">
                        <label style="display:flex;align-items:center;gap:10px;font-size:14px;font-weight:600;cursor:pointer;">
                            <input type="checkbox" name="aktif" id="create_aktif" value="1" @checked(old('aktif', true)) style="width:18px;height:18px;">
                            Proyek aktif
                        </label>
                    </div>
                </div>
                <div class="pbl-modal-footer">
                    <button type="submit" class="pbl-btn-save">
                        @include('components.icon', ['name' => 'plus'])<span>Tambah PBL</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="pbl-modal-backdrop" id="pblEditModal" aria-hidden="true">
        <div class="pbl-modal" role="dialog" aria-labelledby="pblEditTitle">
            <button type="button" class="pbl-modal-close" onclick="closePblEdit()" aria-label="Tutup">
                @include('components.icon', ['name' => 'x'])
            </button>
            <div class="pbl-modal-badge">Edit PBL</div>
            <h3 id="pblEditTitle">Edit Proyek</h3>
            <p class="pbl-modal-sub">Ganti manpro/co-manpro lewat dropdown di bawah, lalu simpan. Mahasiswa tim tetap; yang berubah akses dosen.</p>
            <form method="POST" id="pblEditForm" class="pbl-form-grid">
                @csrf
                @method('PUT')
                <div>
                    <label class="pbl-field-label">Kode EL *</label>
                    <input type="text" name="kode" id="edit_kode" required maxlength="40">
                </div>
                <div>
                    <label class="pbl-field-label">Nama PBL *</label>
                    <input type="text" name="nama" id="edit_nama" required maxlength="200">
                </div>
                @include('admin._pbl_dosen_picker', ['prefix' => 'edit', 'dropdown' => true])
                <div class="pbl-form-row-2">
                    <div>
                        <label class="pbl-field-label">Sesi *</label>
                        <select name="shift_sesi" id="edit_shift">
                            <option value="pagi">Pagi</option>
                            <option value="malam">Malam</option>
                        </select>
                    </div>
                    <div style="display:flex;align-items:center;padding-top:28px;">
                        <label style="display:flex;align-items:center;gap:10px;font-size:14px;font-weight:600;cursor:pointer;">
                            <input type="checkbox" name="aktif" id="edit_aktif" value="1" style="width:18px;height:18px;">
                            Proyek aktif
                        </label>
                    </div>
                </div>
                <div class="pbl-modal-footer">
                    <button type="submit" class="pbl-btn-save">
                        @include('components.icon', ['name' => 'save'])<span>Simpan perubahan</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection

@section('scripts')
<script src="{{ asset('js/pbl-dosen-picker.js') }}?v=1"></script>
<script>
const pblUpdateBase = @json(url('/admin/pbl'));

function openPblEditFromButton(btn) {
    openPblEdit({
        id: btn.dataset.id,
        kode: btn.dataset.kode || '',
        nama: btn.dataset.nama || '',
        manpro: btn.dataset.manpro || '',
        co_manpro: btn.dataset.coManpro || '',
        manpro_user_id: btn.dataset.manproUserId || '',
        co_manpro_user_id: btn.dataset.coManproUserId || '',
        shift_sesi: btn.dataset.shift || 'pagi',
        aktif: btn.dataset.aktif === '1',
    });
}

function openPblEdit(data) {
    document.getElementById('pblEditForm').action = pblUpdateBase + '/' + data.id;
    document.getElementById('pblEditTitle').textContent = data.kode;
    document.getElementById('edit_kode').value = data.kode;
    document.getElementById('edit_nama').value = data.nama;
    const manproSel = document.getElementById('edit_manpro_select');
    const coManproSel = document.getElementById('edit_comanpro_select');
    if (manproSel) manproSel.value = data.manpro_user_id || '';
    if (coManproSel) coManproSel.value = data.co_manpro_user_id || '';
    const manproSearch = document.getElementById('edit_manpro_search');
    const coSearch = document.getElementById('edit_comanpro_search');
    if (manproSearch) {
        manproSearch.value = '';
        manproSearch.dispatchEvent(new Event('input', { bubbles: true }));
    }
    if (coSearch) {
        coSearch.value = '';
        coSearch.dispatchEvent(new Event('input', { bubbles: true }));
    }
    document.getElementById('edit_shift').value = data.shift_sesi;
    document.getElementById('edit_aktif').checked = !!data.aktif;
    const modal = document.getElementById('pblEditModal');
    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden', 'false');
    if (window.pblDosenPickerRefresh) {
        window.pblDosenPickerRefresh(modal);
    }
}

function closePblEdit() {
    const modal = document.getElementById('pblEditModal');
    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden', 'true');
}

document.querySelectorAll('.pbl-edit-btn').forEach(function(btn) {
    btn.addEventListener('click', function() { openPblEditFromButton(this); });
});

document.getElementById('pblEditModal')?.addEventListener('click', function(e) {
    if (e.target === this) closePblEdit();
});

function openPblCreate() {
    const modal = document.getElementById('pblCreateModal');
    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden', 'false');
    if (window.pblDosenPickerRefresh) {
        window.pblDosenPickerRefresh(modal);
    }
}

function closePblCreate() {
    const modal = document.getElementById('pblCreateModal');
    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden', 'true');
}

document.getElementById('pblCreateModal')?.addEventListener('click', function(e) {
    if (e.target === this) closePblCreate();
});

@if(old('_pbl_form') === 'create' && (isset($errors) && $errors->any()))
document.addEventListener('DOMContentLoaded', function() { openPblCreate(); });
@endif

(function () {
    const searchUrl = @json(route('admin.pbl.search'));
    const csrfToken = @json(csrf_token());
    const form = document.getElementById('pblProjectFilterForm');
    const searchInput = document.getElementById('pblProjectSearch');
    const searchField = document.getElementById('pblProjectSearchField');
    const clearBtn = document.getElementById('pblProjectSearchClear');
    const shiftInput = document.getElementById('pblProjectShift');
    const dropdown = document.getElementById('pblProjectSearchDropdown');
    const tbody = document.getElementById('pblProjectTableBody');
    const pagination = document.getElementById('pblProjectPagination');
    const meta = document.getElementById('pblProjectSearchMeta');
    const resetLink = document.getElementById('pblProjectResetLink');
    if (!form || !searchInput || !tbody || !dropdown) return;

    let searchTimer = null;
    let activeIndex = -1;
    let latestRequest = 0;
    let suppressDropdown = false;
    const initialPaginationHtml = pagination ? pagination.innerHTML : '';

    function esc(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function syncClearVisibility() {
        searchField?.classList.toggle('has-value', (searchInput.value || '').trim() !== '');
    }

    function currentShift() {
        return (shiftInput?.value || '').trim();
    }

    function hideDropdown() {
        dropdown.classList.remove('show');
        dropdown.innerHTML = '';
        activeIndex = -1;
        searchInput.setAttribute('aria-expanded', 'false');
    }

    function updateActive() {
        dropdown.querySelectorAll('.pbl-search-item').forEach((el, idx) => {
            el.classList.toggle('is-active', idx === activeIndex);
        });
    }

    function renderDropdown(suggestions, query) {
        if (suppressDropdown || !query) {
            hideDropdown();
            return;
        }
        if (!suggestions.length) {
            dropdown.innerHTML = '<div class="pbl-search-empty">Proyek tidak ditemukan</div>';
            dropdown.classList.add('show');
            searchInput.setAttribute('aria-expanded', 'true');
            activeIndex = -1;
            return;
        }

        dropdown.innerHTML = suggestions.map((item, idx) => `
            <button type="button" class="pbl-search-item" role="option" data-idx="${idx}" data-kode="${esc(item.kode)}" data-nama="${esc(item.nama)}">
                <span class="pbl-search-item-icon"><i data-lucide="folder-kanban"></i></span>
                <span class="pbl-search-item-text">
                    <span class="pbl-search-item-label">${esc(item.label || (item.kode + ' · ' + item.nama))}</span>
                    <span class="pbl-search-item-group">${esc(item.group || '')}</span>
                </span>
            </button>
        `).join('');
        dropdown.classList.add('show');
        searchInput.setAttribute('aria-expanded', 'true');
        activeIndex = 0;
        updateActive();
        if (window.refreshLucideIcons) window.refreshLucideIcons();
        else if (window.lucide && window.lucide.createIcons) window.lucide.createIcons();

        dropdown.querySelectorAll('.pbl-search-item').forEach((btn) => {
            btn.addEventListener('mousedown', function (e) {
                e.preventDefault();
                const kode = btn.getAttribute('data-kode') || '';
                const nama = btn.getAttribute('data-nama') || '';
                suppressDropdown = true;
                searchInput.value = kode || nama;
                syncClearVisibility();
                hideDropdown();
                runSearch({ showDropdown: false });
                setTimeout(() => { suppressDropdown = false; }, 250);
            });
        });
    }

    function bindRowActions() {
        tbody.querySelectorAll('.pbl-edit-btn').forEach(function (btn) {
            btn.addEventListener('click', function () { openPblEditFromButton(btn); });
        });
        if (window.refreshLucideIcons) window.refreshLucideIcons();
        else if (window.lucide && window.lucide.createIcons) window.lucide.createIcons();
    }

    function renderTable(projects) {
        if (!projects.length) {
            tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:28px;color:var(--text-muted);">Tidak ada proyek pada filter ini. Coba kosongkan pencarian atau ganti sesi.</td></tr>';
            return;
        }

        tbody.innerHTML = projects.map((p) => {
            const status = p.aktif
                ? '<span class="status-badge success">Aktif</span>'
                : '<span class="status-badge warning">Nonaktif</span>';
            const shiftLabel = String(p.shift_sesi || '').charAt(0).toUpperCase() + String(p.shift_sesi || '').slice(1);
            return `
                <tr>
                    <td style="font-weight:700;font-family:monospace;font-size:12px;">${esc(p.kode)}</td>
                    <td style="font-weight:600;">${esc(p.nama)}</td>
                    <td>${esc(p.manpro_name)}</td>
                    <td>${esc(p.co_manpro_name)}</td>
                    <td><span class="status-badge" style="background:rgba(74,144,217,0.12);color:#2563eb;">${esc(shiftLabel)}</span></td>
                    <td>${esc(p.jumlah_mhs)}</td>
                    <td>${status}</td>
                    <td style="text-align:right;white-space:nowrap;">
                        <button type="button" class="pbl-action-icon pbl-edit-btn" title="Edit"
                            data-id="${esc(p.id)}"
                            data-kode="${esc(p.kode)}"
                            data-nama="${esc(p.nama)}"
                            data-manpro="${esc(p.manpro)}"
                            data-co-manpro="${esc(p.co_manpro)}"
                            data-manpro-user-id="${esc(p.manpro_user_id || '')}"
                            data-co-manpro-user-id="${esc(p.co_manpro_user_id || '')}"
                            data-shift="${esc(p.shift_sesi)}"
                            data-aktif="${p.aktif ? '1' : '0'}"><i data-lucide="pencil"></i></button>
                        <form method="POST" action="${esc(p.destroy_url)}" class="inline-action-form"
                            data-swal-confirm="Hapus proyek ${esc(p.kode)}? Mahasiswa di tim ini akan kehilangan penempatan."
                            data-swal-confirm-button="Ya, hapus" data-swal-confirm-color="#f43f5e"
                            style="display:inline;">
                            <input type="hidden" name="_token" value="${esc(csrfToken)}">
                            <input type="hidden" name="_method" value="DELETE">
                            <button type="submit" class="pbl-action-icon pbl-action-icon--danger" title="Hapus"><i data-lucide="trash-2"></i></button>
                        </form>
                    </td>
                </tr>
            `;
        }).join('');
        bindRowActions();
    }

    function updateMeta(data) {
        if (!meta) return;
        const q = data.q || '';
        const shift = data.shift || '';
        const total = data.total ?? 0;
        let html = '';
        if (q || shift) {
            html = `Hasil filter: <strong>${total}</strong> proyek`;
            if (q) html += ` · kata kunci “${esc(q)}”`;
            if (shift) html += ` · sesi ${esc(shift)}`;
        } else {
            html = 'Menampilkan semua proyek · ketik untuk lihat saran dropdown (tanpa refresh)';
        }
        const span = meta.querySelector('span') || meta;
        if (meta.querySelector('span')) {
            meta.querySelector('span').innerHTML = html;
        } else {
            meta.innerHTML = `<span>${html}</span>`;
        }
        if (resetLink) {
            resetLink.style.display = (q || shift) ? '' : 'none';
        }
    }

    function syncUrl(q, shift) {
        const url = new URL(window.location.href);
        if (q) url.searchParams.set('q', q); else url.searchParams.delete('q');
        if (shift) url.searchParams.set('shift', shift); else url.searchParams.delete('shift');
        url.searchParams.delete('page');
        url.hash = 'daftar-proyek';
        window.history.replaceState({}, '', url.pathname + url.search + url.hash);
    }

    async function runSearch(opts = {}) {
        const showDropdown = opts.showDropdown !== false;
        const q = (searchInput.value || '').trim();
        const shift = currentShift();
        const reqId = ++latestRequest;

        tbody.classList.add('pbl-table-loading');

        try {
            const url = new URL(searchUrl, window.location.origin);
            if (q) url.searchParams.set('q', q);
            if (shift) url.searchParams.set('shift', shift);

            const res = await fetch(url.toString(), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            if (!res.ok) throw new Error('search failed');
            const data = await res.json();
            if (reqId !== latestRequest) return;

            renderTable(data.projects || []);
            updateMeta(data);
            syncUrl(data.q || '', data.shift || '');
            if (pagination) {
                if (data.q || data.shift) {
                    pagination.innerHTML = `<div style="font-size:12px;color:var(--text-muted);">Menampilkan ${data.projects?.length || 0} dari ${data.total || 0} hasil (live filter)</div>`;
                } else {
                    pagination.innerHTML = initialPaginationHtml;
                }
            }
            if (showDropdown) {
                renderDropdown(data.suggestions || [], q);
            } else {
                hideDropdown();
            }
        } catch (err) {
            if (reqId !== latestRequest) return;
            if (showDropdown && q) {
                dropdown.innerHTML = '<div class="pbl-search-empty">Gagal memuat saran</div>';
                dropdown.classList.add('show');
            }
        } finally {
            if (reqId === latestRequest) {
                tbody.classList.remove('pbl-table-loading');
            }
        }
    }

    function scheduleSearch() {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => runSearch({ showDropdown: true }), 280);
    }

    searchInput.addEventListener('input', function () {
        syncClearVisibility();
        scheduleSearch();
    });

    searchInput.addEventListener('focus', function () {
        if ((searchInput.value || '').trim()) {
            runSearch({ showDropdown: true });
        }
    });

    searchInput.addEventListener('keydown', function (e) {
        const items = [...dropdown.querySelectorAll('.pbl-search-item')];
        if (e.key === 'ArrowDown' && items.length) {
            e.preventDefault();
            activeIndex = (activeIndex + 1) % items.length;
            updateActive();
            return;
        }
        if (e.key === 'ArrowUp' && items.length) {
            e.preventDefault();
            activeIndex = (activeIndex - 1 + items.length) % items.length;
            updateActive();
            return;
        }
        if (e.key === 'Enter') {
            e.preventDefault();
            clearTimeout(searchTimer);
            if (activeIndex >= 0 && items[activeIndex]) {
                items[activeIndex].dispatchEvent(new Event('mousedown'));
            } else {
                suppressDropdown = true;
                runSearch({ showDropdown: false }).then(() => {
                    setTimeout(() => { suppressDropdown = false; }, 250);
                });
            }
            return;
        }
        if (e.key === 'Escape') {
            hideDropdown();
        }
    });

    clearBtn?.addEventListener('click', function () {
        searchInput.value = '';
        syncClearVisibility();
        hideDropdown();
        clearTimeout(searchTimer);
        runSearch({ showDropdown: false });
    });

    document.querySelectorAll('[data-pbl-shift]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const value = btn.getAttribute('data-pbl-shift') || '';
            if (shiftInput) shiftInput.value = value;
            document.querySelectorAll('[data-pbl-shift]').forEach(function (el) {
                el.classList.toggle('is-active', el === btn);
            });
            clearTimeout(searchTimer);
            runSearch({ showDropdown: !!(searchInput.value || '').trim() });
        });
    });

    document.addEventListener('click', function (e) {
        if (!dropdown.contains(e.target) && !searchField?.contains(e.target)) {
            hideDropdown();
        }
    });

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        clearTimeout(searchTimer);
        runSearch({ showDropdown: false });
    });

    resetLink?.addEventListener('click', function (e) {
        e.preventDefault();
        searchInput.value = '';
        if (shiftInput) shiftInput.value = '';
        document.querySelectorAll('[data-pbl-shift]').forEach(function (el) {
            el.classList.toggle('is-active', (el.getAttribute('data-pbl-shift') || '') === '');
        });
        syncClearVisibility();
        hideDropdown();
        runSearch({ showDropdown: false });
    });

    syncClearVisibility();
    // If page opened with query params, keep server-rendered table; dropdown on focus only.
})();

(function () {
    const form = document.getElementById('pblMhsFilterForm');
    const searchInput = document.getElementById('pblMhsSearch');
    const searchField = document.getElementById('pblMhsSearchField');
    const clearBtn = document.getElementById('pblMhsSearchClear');
    if (!form) return;

    function buildUrl() {
        const params = new URLSearchParams(new FormData(form));
        ['q', 'shift', 'mhs_q', 'mhs_filter', 'mhs_tim', 'page'].forEach(function (key) {
            if (!params.get(key)) {
                params.delete(key);
            }
        });
        if (params.get('mhs_filter') === 'semua') params.delete('mhs_filter');
        const base = form.getAttribute('action').split('#')[0];
        const qs = params.toString();
        return base + (qs ? '?' + qs : '') + '#penempatan-mhs';
    }

    function go() {
        const nextUrl = buildUrl();
        const next = new URL(nextUrl, window.location.origin);
        const current = new URL(window.location.href);
        if (next.pathname + next.search + next.hash !== current.pathname + current.search + current.hash) {
            window.location.href = next.href;
        }
    }

    let timer = null;
    function syncClear() {
        searchField?.classList.toggle('has-value', (searchInput?.value || '').trim() !== '');
    }

    searchInput?.addEventListener('input', function () {
        syncClear();
        clearTimeout(timer);
        timer = setTimeout(go, 400);
    });
    searchInput?.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            clearTimeout(timer);
            go();
        }
    });
    clearBtn?.addEventListener('click', function () {
        if (searchInput) searchInput.value = '';
        syncClear();
        clearTimeout(timer);
        go();
    });
    document.getElementById('pblMhsFilterStatus')?.addEventListener('change', go);
    document.getElementById('pblMhsFilterTim')?.addEventListener('change', go);
    syncClear();
})();

(function () {
    document.querySelectorAll('[data-pbl-dropzone]').forEach(function (zone) {
        const input = zone.querySelector('[data-pbl-file]');
        const nameEl = zone.querySelector('[data-pbl-filename]');
        const clearBtn = zone.querySelector('[data-pbl-clear]');
        if (!input) return;

        function setFile(file) {
            if (!file) {
                zone.classList.remove('is-ready');
                if (nameEl) nameEl.textContent = '—';
                return;
            }
            zone.classList.add('is-ready');
            if (nameEl) nameEl.textContent = file.name;
        }

        input.addEventListener('change', function () {
            setFile(input.files && input.files[0] ? input.files[0] : null);
        });

        ['dragenter', 'dragover'].forEach(function (evt) {
            zone.addEventListener(evt, function (e) {
                e.preventDefault();
                e.stopPropagation();
                zone.classList.add('is-drag');
            });
        });
        ['dragleave', 'drop'].forEach(function (evt) {
            zone.addEventListener(evt, function (e) {
                e.preventDefault();
                e.stopPropagation();
                zone.classList.remove('is-drag');
            });
        });
        zone.addEventListener('drop', function (e) {
            const file = e.dataTransfer && e.dataTransfer.files ? e.dataTransfer.files[0] : null;
            if (!file) return;
            const dt = new DataTransfer();
            dt.items.add(file);
            input.files = dt.files;
            setFile(file);
        });
        clearBtn?.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            input.value = '';
            setFile(null);
        });
    });
})();

(function () {
    const sections = [
        { id: 'penempatan-mhs', keys: ['mhs_q', 'mhs_filter', 'mhs_tim'] },
        { id: 'daftar-proyek', keys: ['q', 'shift', 'page'] },
    ];

    const params = new URLSearchParams(window.location.search);
    const hashId = window.location.hash ? window.location.hash.slice(1) : '';
    const targetId = sections.find((s) => s.id === hashId)?.id
        || sections.find((s) => s.keys.some((key) => params.has(key)))?.id;

    if (!targetId) {
        return;
    }

    function scrollToSection() {
        const el = document.getElementById(targetId);
        if (el) {
            el.scrollIntoView({ block: 'start' });
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        requestAnimationFrame(scrollToSection);
        setTimeout(scrollToSection, 120);
    });
})();

(function () {
    const modal = document.getElementById('pblTeamDetailModal');
    const body = document.getElementById('pblTeamDetailBody');
    const closeBtn = document.getElementById('pblTeamDetailClose');
    if (!modal || !body) return;

    function openTeamDetail(detailId) {
        const source = document.getElementById(detailId);
        if (!source) return;

        body.innerHTML = source.innerHTML;
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        if (window.refreshLucideIcons) window.refreshLucideIcons();
    }

    function closeTeamDetail() {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        body.innerHTML = '';
        document.body.style.overflow = '';
    }

    document.querySelectorAll('.js-pbl-team-open').forEach(function (btn) {
        btn.addEventListener('click', function () {
            openTeamDetail(btn.getAttribute('data-detail-id'));
        });
    });

    closeBtn?.addEventListener('click', closeTeamDetail);
    modal.addEventListener('click', function (e) {
        if (e.target === modal) closeTeamDetail();
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal.classList.contains('is-open')) {
            closeTeamDetail();
        }
    });
})();
</script>
@endsection
