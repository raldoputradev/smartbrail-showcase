@extends('layouts.dashboard')

@section('title', 'Manajemen Mesin IoT')
@section('page-title', 'Manajemen Mesin IoT')
@section('page-subtitle', 'Registrasi ESP32, API key, status heartbeat & tipe perangkat')

@section('sidebar-nav')
    @include('admin._sidebar')
@endsection

@section('content')
    @php
        $st = $stats ?? ['total' => 0, 'active' => 0, 'inactive' => 0, 'online' => 0, 'offline' => 0, 'by_type' => []];
        $threshold = \App\Models\MesinIot::ONLINE_THRESHOLD_MINUTES;
        $apiBase = rtrim(url('/api/mesin'), '/');
        $setupKey = session('mesin_plain_key');
        $setupId = session('mesin_plain_id');
        $setupName = session('mesin_plain_name', $setupId);
        $setupTipe = session('mesin_plain_tipe', 'absensi');
        $setupMode = session('mesin_setup_mode', 'create');
        $showSetup = filled($setupKey);
    @endphp

    @if($showSetup)
        <div class="iot-modal-backdrop is-open" id="setupWizardModal" role="dialog" aria-modal="true">
            <div class="iot-modal iot-wizard">
                <button type="button" class="iot-modal-close" onclick="closeSetupWizard()" aria-label="Tutup">×</button>

                <div class="wiz-toast" id="wizToast">
                    <span class="wiz-toast__icon">@include('components.icon', ['name' => 'check-circle-2'])</span>
                    <div>
                        <strong>{{ $setupMode === 'regenerate' ? 'API key baru siap' : 'Mesin berhasil didaftarkan' }}</strong>
                        <small>{{ $setupName }} · {{ $setupId }}</small>
                    </div>
                </div>

                <div class="wiz-steps" id="wizSteps">
                    <button type="button" class="wiz-step is-active" data-step="1"><span>1</span> Identitas</button>
                    <button type="button" class="wiz-step" data-step="2"><span>2</span> Kredensial</button>
                    <button type="button" class="wiz-step" data-step="3"><span>3</span> Firmware</button>
                    <button type="button" class="wiz-step" data-step="4"><span>4</span> Verifikasi</button>
                </div>

                <div class="wiz-panel is-active" data-panel="1">
                    <h3>Setup mesin siap dimulai</h3>
                    <p>Ikuti 4 langkah ini seperti provisioning VPS — simpan kredensial, tempel ke firmware, lalu uji heartbeat.</p>
                    <div class="wiz-meta">
                        <div><span>ID mesin</span><strong>{{ $setupId }}</strong></div>
                        <div><span>Nama</span><strong>{{ $setupName }}</strong></div>
                        <div><span>Tipe</span><strong>{{ \Illuminate\Support\Str::title(str_replace(['_', '-'], ' ', $setupTipe)) }}</strong></div>
                        <div><span>Mode</span><strong>{{ $setupMode === 'regenerate' ? 'Regenerate key' : 'Mesin baru' }}</strong></div>
                    </div>
                    <div class="wiz-actions">
                        <button type="button" class="mesin-btn mesin-btn--primary" onclick="wizGo(2)">Lanjut ke kredensial →</button>
                    </div>
                </div>

                <div class="wiz-panel" data-panel="2">
                    <h3>Salin API key (sekali tampil)
                        <button type="button" class="help-q" title="Lihat di firmware" onclick="openFirmwareHelp('apikey')" aria-label="Bantuan API key">?</button>
                    </h3>
                    <p>Key hanya ditampilkan sekarang. Setelah ditutup, sistem hanya menyimpan hash dan key tidak bisa dilihat ulang.</p>
                    <label class="modal-label">API key</label>
                    <div class="api-copy-row">
                        <input id="plainApiKey" type="text" readonly value="{{ $setupKey }}">
                        <button type="button" onclick="copyText('plainApiKey', 'API key tersalin')">Salin</button>
                    </div>
                    <label class="modal-label" style="margin-top:12px;">Header siap pakai</label>
                    <div class="api-copy-row">
                        <input id="plainApiHeader" type="text" readonly value="X-API-KEY: {{ $setupKey }}">
                        <button type="button" onclick="copyText('plainApiHeader', 'Header tersalin')">Salin</button>
                    </div>
                    <div class="wiz-warn">
                        @include('components.icon', ['name' => 'shield-alert'])
                        <span>Jangan share key di chat publik. Simpan di secrets firmware / EEPROM.</span>
                    </div>
                    <div class="wiz-actions">
                        <button type="button" class="mesin-btn mesin-btn--ghost" onclick="wizGo(1)">← Kembali</button>
                        <button type="button" class="mesin-btn mesin-btn--primary" onclick="wizGo(3)">Lanjut firmware →</button>
                    </div>
                </div>

                <div class="wiz-panel" data-panel="3">
                    <h3>Konfigurasi firmware ESP32
                        <button type="button" class="help-q" title="Lihat contoh config.h" onclick="openFirmwareHelp('config')" aria-label="Bantuan firmware">?</button>
                    </h3>
                    <p>Tempel nilai berikut ke sketch / firmware mesin. Klik <strong>?</strong> untuk melihat contoh di program.</p>
                    <div class="wiz-code-grid">
                        <div class="wiz-code">
                            <div class="wiz-code__head">
                                <span>Base URL API</span>
                                <button type="button" class="mesin-btn mesin-btn--ghost mesin-btn--sm" onclick="copyRaw(@json($apiBase), 'Base URL tersalin')">Salin</button>
                            </div>
                            <code>{{ $apiBase }}</code>
                        </div>
                        <div class="wiz-code">
                            <div class="wiz-code__head">
                                <span>ID mesin <button type="button" class="help-q help-q--sm" onclick="openFirmwareHelp('id')" aria-label="Bantuan ID">?</button></span>
                                <button type="button" class="mesin-btn mesin-btn--ghost mesin-btn--sm" onclick="copyRaw(@json($setupId), 'ID mesin tersalin')">Salin</button>
                            </div>
                            <code>{{ $setupId }}</code>
                        </div>
                        <div class="wiz-code wiz-code--full">
                            <div class="wiz-code__head">
                                <span>Endpoint penting</span>
                            </div>
                            <pre>POST/GET  {{ $apiBase }}/heartbeat
@if($setupTipe === 'absensi')
POST      {{ $apiBase }}/tap
GET       {{ $apiBase }}/fingerprint/sync
@else
GET       {{ $apiBase }}/fingerprint/sync
@endif</pre>
                        </div>
                        <div class="wiz-code wiz-code--full">
                            <div class="wiz-code__head">
                                <span>Contoh request heartbeat (curl)</span>
                                <button type="button" class="mesin-btn mesin-btn--ghost mesin-btn--sm" onclick="copyRaw(document.getElementById('wizCurl').textContent, 'Curl tersalin')">Salin</button>
                            </div>
                            <pre id="wizCurl">curl -X POST "{{ $apiBase }}/heartbeat" \
  -H "X-API-KEY: {{ $setupKey }}" \
  -H "Content-Type: application/json"</pre>
                        </div>
                    </div>
                    <div class="wiz-actions">
                        <button type="button" class="mesin-btn mesin-btn--ghost" onclick="wizGo(2)">← Kembali</button>
                        <button type="button" class="mesin-btn mesin-btn--primary" onclick="wizGo(4)">Lanjut verifikasi →</button>
                    </div>
                </div>

                <div class="wiz-panel" data-panel="4">
                    <h3>Verifikasi koneksi</h3>
                    <p>Setelah firmware terpasang, mesin harus kirim heartbeat (atau tap) dalam ≤ {{ $threshold }} menit agar status berubah Online.</p>
                    <ol class="wiz-checklist">
                        <li>Flash firmware dengan ID + API key yang benar</li>
                        <li>Pastikan ESP32 online ke jaringan yang sama / internet</li>
                        <li>Panggil <code>/heartbeat</code> atau lakukan tap uji</li>
                        <li>Buka Monitoring untuk cek status realtime</li>
                    </ol>
                    <div class="wiz-actions wiz-actions--end">
                        <button type="button" class="mesin-btn mesin-btn--ghost" onclick="wizGo(3)">← Kembali</button>
                        <a href="{{ route('admin.monitoring') }}" class="mesin-btn mesin-btn--ghost">Buka Monitoring</a>
                        <button type="button" class="mesin-btn mesin-btn--primary" onclick="closeSetupWizard(true)">Selesai, saya sudah simpan</button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div class="mesin-toolbar animate-in">
        <div class="mesin-toolbar__left">
            <span class="mesin-live"><span class="mesin-live__dot"></span>FLEET</span>
            <div class="mesin-toolbar__meta">
                <strong>Armada perangkat SIMALAS</strong>
                <small>Online = heartbeat/tap ≤ {{ $threshold }} menit</small>
            </div>
        </div>
        <div class="mesin-toolbar__right">
            <a href="{{ route('admin.monitoring') }}" class="mesin-btn mesin-btn--ghost">@include('components.icon', ['name' => 'radio']) Monitoring</a>
            <button type="button" class="mesin-btn mesin-btn--primary" onclick="openRegisterModal()">
                @include('components.icon', ['name' => 'plus']) Daftar mesin baru
            </button>
        </div>
    </div>

    <div class="mesin-kpi animate-in">
        <a href="{{ route('admin.mesin.index') }}" class="mesin-kpi__item" style="--kpi:#0284c7;">
            <div class="mesin-kpi__icon">@include('components.icon', ['name' => 'cpu'])</div>
            <div>
                <span>Total mesin</span>
                <strong>{{ number_format($st['total']) }}</strong>
                <small>Terdaftar di sistem</small>
            </div>
        </a>
        <a href="{{ route('admin.mesin.index', ['status' => 'online']) }}" class="mesin-kpi__item" style="--kpi:#059669;">
            <div class="mesin-kpi__icon">@include('components.icon', ['name' => 'wifi'])</div>
            <div>
                <span>Online</span>
                <strong>{{ number_format($st['online']) }}</strong>
                <small>Heartbeat aktif</small>
            </div>
        </a>
        <a href="{{ route('admin.mesin.index', ['status' => 'offline']) }}" class="mesin-kpi__item" style="--kpi:#d97706;">
            <div class="mesin-kpi__icon">@include('components.icon', ['name' => 'wifi-off'])</div>
            <div>
                <span>Offline</span>
                <strong>{{ number_format($st['offline']) }}</strong>
                <small>Aktif, belum heartbeat</small>
            </div>
        </a>
        <a href="{{ route('admin.mesin.index', ['status' => 'nonaktif']) }}" class="mesin-kpi__item" style="--kpi:#64748b;">
            <div class="mesin-kpi__icon">@include('components.icon', ['name' => 'power'])</div>
            <div>
                <span>Nonaktif</span>
                <strong>{{ number_format($st['inactive']) }}</strong>
                <small>Dinonaktifkan admin</small>
            </div>
        </a>
    </div>

    <div class="mesin-cta animate-in">
        <div class="mesin-cta__text">
            <strong>Butuh menambah perangkat?</strong>
            <span>Klik tombol di samping untuk mendaftarkan mesin baru. Setelah itu sistem akan menampilkan API key dan panduan setup.</span>
        </div>
        <button type="button" class="mesin-btn mesin-btn--primary" id="btnOpenRegister" onclick="openRegisterModal()">
            @include('components.icon', ['name' => 'plus']) Daftar mesin baru
        </button>
    </div>

    <div class="iot-modal-backdrop" id="registerMachineModal" @if($errors->any() || old('id_mesin')) style="display:flex;" @endif>
        <div class="iot-modal iot-register">
            <button type="button" class="iot-modal-close" onclick="closeRegisterModal()" aria-label="Tutup">×</button>
            <div class="iot-modal-badge" style="background:rgba(2,132,199,.14);color:#0284c7;">DAFTAR MESIN</div>
            <h3>Daftarkan mesin baru</h3>
            <p>Isi 3 data di bawah. Setelah disimpan, muncul wizard berisi API key + cara pasang di ESP32.</p>

            <div class="iot-register__grid">
                <form method="POST" action="{{ route('admin.mesin.store') }}" class="mesin-form" id="mesinCreateForm">
                    @csrf
                    <div class="filter-field">
                        <label>
                            ID Mesin <em>*</em>
                            <button type="button" class="help-q" title="Lihat di firmware" onclick="openFirmwareHelp('id')" aria-label="Bantuan ID mesin">?</button>
                        </label>
                        <input type="text" name="id_mesin" id="createIdMesin" required
                            class="input-id-mesin"
                            placeholder="contoh: ABSEN-LAB-A1"
                            value="{{ old('id_mesin') }}"
                            autocomplete="off"
                            spellcheck="false"
                            maxlength="100"
                            inputmode="text"
                            oninput="forceUpperId(this)">
                        <small class="field-hint">Wajib huruf besar · unik · mis. ABSEN-LAB-A1 / LOKER-LAB-1</small>
                        @error('id_mesin') <small class="field-error">{{ $message }}</small> @enderror
                    </div>
                    <div class="filter-field">
                        <label>
                            Nama mesin
                            <button type="button" class="help-q" title="Penjelasan nama mesin" onclick="openFirmwareHelp('nama')" aria-label="Bantuan nama mesin">?</button>
                        </label>
                        <input type="text" name="nama_mesin" placeholder="contoh: Mesin Absensi Lab A1" value="{{ old('nama_mesin') }}">
                        <small class="field-hint">Boleh campur huruf · tampil di daftar & monitoring · mis. Mesin Absensi Lab A1</small>
                    </div>
                    <div class="filter-field">
                        <label>
                            Tipe perangkat <em>*</em>
                            <button type="button" class="help-q" title="Penjelasan tipe" onclick="openFirmwareHelp('tipe')" aria-label="Bantuan tipe perangkat">?</button>
                        </label>
                        <select name="tipe" required onchange="toggleCustomType(this, 'createCustomType')">
                            @foreach($typeOptions as $typeValue => $typeLabel)
                                <option value="{{ $typeValue }}" @selected(old('tipe', 'absensi') === $typeValue)>{{ $typeLabel }}</option>
                            @endforeach
                            <option value="__custom" @selected(old('tipe') === '__custom')>+ Tipe baru</option>
                        </select>
                        <small class="field-hint">Pilih Absensi / Loker, atau buat tipe sendiri</small>
                    </div>
                    <div class="filter-field">
                        <label>Nama tipe baru</label>
                        <input id="createCustomType" type="text" name="tipe_custom"
                            placeholder="contoh: pintu"
                            value="{{ old('tipe_custom') }}"
                            style="{{ old('tipe') === '__custom' ? '' : 'display:none;' }}">
                        <small class="field-hint">Hanya jika memilih “+ Tipe baru” · mis. pintu / gate / sensor</small>
                    </div>
                    <div class="mesin-modal-actions" style="margin-top:8px;">
                        <button type="button" class="mesin-btn mesin-btn--ghost" onclick="closeRegisterModal()">Batal</button>
                        <button type="submit" class="mesin-btn mesin-btn--primary" id="mesinCreateSubmit">
                            @include('components.icon', ['name' => 'check']) Simpan & lanjut setup
                        </button>
                    </div>
                </form>

                <aside class="mesin-tips mesin-tips--modal">
                    <strong class="mesin-tips__title">Yang perlu disiapkan</strong>
                    <div class="mesin-tips__item">
                        <span class="mesin-tips__n">1</span>
                        <div>
                            <strong>ID mesin <button type="button" class="help-q help-q--sm" onclick="openFirmwareHelp('id')" aria-label="Bantuan ID">?</button></strong>
                            <small>Huruf besar · ditulis di firmware sebagai <code>ID_MESIN</code></small>
                        </div>
                    </div>
                    <div class="mesin-tips__item">
                        <span class="mesin-tips__n">2</span>
                        <div>
                            <strong>API key <button type="button" class="help-q help-q--sm" onclick="openFirmwareHelp('apikey')" aria-label="Bantuan API key">?</button></strong>
                            <small>Muncul sekali setelah simpan · ditempel ke <code>API_KEY</code></small>
                        </div>
                    </div>
                    <div class="mesin-tips__item">
                        <span class="mesin-tips__n">3</span>
                        <div>
                            <strong>Jaringan / WiFi <button type="button" class="help-q help-q--sm" onclick="openFirmwareHelp('config')" aria-label="Bantuan config">?</button></strong>
                            <small>ESP32 harus bisa akses server SIMALAS</small>
                        </div>
                    </div>
                </aside>
            </div>
        </div>
    </div>

    <div class="iot-modal-backdrop" id="firmwareHelpModal">
        <div class="iot-modal iot-help">
            <button type="button" class="iot-modal-close" onclick="closeFirmwareHelp()" aria-label="Tutup">×</button>
            <div class="iot-modal-badge" style="background:rgba(2,132,199,.14);color:#0284c7;">PANDUAN FIRMWARE</div>
            <h3 id="firmwareHelpTitle">Contoh di program ESP32</h3>
            <p id="firmwareHelpDesc" class="iot-help__desc"></p>
            <div class="iot-help__img-wrap">
                <img id="firmwareHelpImg" src="" alt="Contoh kode firmware" class="iot-help__img">
            </div>
            <div class="iot-help__code" id="firmwareHelpCode"></div>
            <div class="mesin-modal-actions">
                <button type="button" class="mesin-btn mesin-btn--primary" onclick="closeFirmwareHelp()">Mengerti</button>
            </div>
        </div>
    </div>

    <div class="card animate-in mesin-list-card">
        <div class="card-header mesin-card-head mesin-list-head">
            <div>
                <h3>Daftar mesin IoT</h3>
                <span>{{ $mesin->total() }} mesin · urut heartbeat terbaru</span>
            </div>
            <div class="mesin-list-head__tools">
                <button type="button" class="mesin-btn mesin-btn--primary mesin-btn--sm" onclick="openRegisterModal()">
                    @include('components.icon', ['name' => 'plus']) Daftar mesin
                </button>
                <form method="GET" action="{{ route('admin.mesin.index') }}" class="mesin-filter">
                    <input type="text" name="q" value="{{ request('q') }}" placeholder="Cari ID / nama…">
                    <select name="tipe">
                        <option value="">Semua tipe</option>
                        @foreach($typeOptions as $typeValue => $typeLabel)
                            <option value="{{ $typeValue }}" @selected(request('tipe') === $typeValue)>{{ $typeLabel }}</option>
                        @endforeach
                    </select>
                    <select name="status">
                        <option value="">Semua status</option>
                        <option value="online" @selected(request('status') === 'online')>Online</option>
                        <option value="offline" @selected(request('status') === 'offline')>Offline</option>
                        <option value="aktif" @selected(request('status') === 'aktif')>Aktif</option>
                        <option value="nonaktif" @selected(request('status') === 'nonaktif')>Nonaktif</option>
                    </select>
                    <button type="submit" class="mesin-btn mesin-btn--ghost mesin-btn--sm">Filter</button>
                    <a href="{{ route('admin.mesin.index') }}" class="mesin-btn mesin-btn--ghost mesin-btn--sm">Reset</a>
                </form>
            </div>
        </div>
        <div class="card-body" style="padding:0;">
            <div class="mesin-table-wrap">
                <table class="mesin-table">
                    <thead>
                        <tr>
                            <th>Mesin</th>
                            <th>Tipe</th>
                            <th>Koneksi</th>
                            <th>Status</th>
                            <th>Aksi</th>
                            <th>Terakhir update</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($mesin as $m)
                            @php
                                $online = $m->isOnline();
                                $conn = !$m->is_active ? 'inactive' : ($online ? 'online' : 'offline');
                                $connLabel = match ($conn) {
                                    'online' => 'Online',
                                    'offline' => 'Offline',
                                    default => 'Nonaktif',
                                };
                            @endphp
                            <tr>
                                <td>
                                    <div class="mesin-id-cell">
                                        <span class="mesin-dot mesin-dot--{{ $conn }}"></span>
                                        <div>
                                            <strong>{{ $m->nama_mesin ?: $m->id_mesin }}</strong>
                                            <small>{{ $m->id_mesin }}</small>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="mesin-chip">{{ \Illuminate\Support\Str::title(str_replace(['_', '-'], ' ', $m->tipe)) }}</span></td>
                                <td>
                                    <span class="mesin-badge mesin-badge--{{ $conn }}">{{ $connLabel }}</span>
                                    <small class="mesin-seen">
                                        {{ $m->last_seen_at ? $m->last_seen_at->diffForHumans() : 'Belum heartbeat' }}
                                    </small>
                                </td>
                                <td>
                                    @if($m->is_active)
                                        <span class="mesin-badge mesin-badge--online">Aktif</span>
                                    @else
                                        <span class="mesin-badge mesin-badge--inactive">Nonaktif</span>
                                    @endif
                                </td>
                                <td class="mesin-aksi-cell">
                                    <div class="mesin-actions">
                                        <form method="POST" action="{{ route('admin.mesin.toggle', $m) }}">
                                            @csrf
                                            <button type="submit" class="mesin-icon-btn" title="{{ $m->is_active ? 'Nonaktifkan' : 'Aktifkan' }}">
                                                @include('components.icon', ['name' => $m->is_active ? 'power' : 'power-off'])
                                            </button>
                                        </form>
                                        <button type="button"
                                            class="mesin-icon-btn"
                                            title="Edit"
                                            data-update-url="{{ route('admin.mesin.update', $m) }}"
                                            data-id-mesin="{{ $m->id_mesin }}"
                                            data-nama-mesin="{{ $m->nama_mesin }}"
                                            data-tipe="{{ $m->tipe }}"
                                            data-active="{{ $m->is_active ? '1' : '0' }}"
                                            onclick="openEditMachine(this)">
                                            @include('components.icon', ['name' => 'pencil'])
                                        </button>
                                        <form method="POST" action="{{ route('admin.mesin.regenerate', $m) }}"
                                            data-swal-confirm
                                            data-swal-title="Generate ulang API key?"
                                            data-swal-text="API key lama untuk {{ $m->id_mesin }} akan langsung tidak berlaku. Wizard setup akan terbuka setelahnya."
                                            data-swal-icon="warning"
                                            data-swal-confirm-button="Ya, Generate"
                                            data-swal-confirm-color="#0284c7">
                                            @csrf
                                            <button type="submit" class="mesin-icon-btn" title="Regenerate API key">
                                                @include('components.icon', ['name' => 'key-round'])
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.mesin.destroy', $m) }}"
                                            data-swal-confirm
                                            data-swal-title="Hapus mesin?"
                                            data-swal-text="Mesin {{ $m->id_mesin }} akan dihapus dari sistem."
                                            data-swal-icon="warning"
                                            data-swal-confirm-button="Ya, Hapus"
                                            data-swal-confirm-color="#be123c">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="mesin-icon-btn mesin-icon-btn--danger" title="Hapus">
                                                @include('components.icon', ['name' => 'trash-2'])
                                            </button>
                                        </form>
                                    </div>
                                </td>
                                <td class="mesin-muted mesin-update-cell">
                                    {{ $m->updated_at?->translatedFormat('d M Y · H:i') }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="mesin-empty">Belum ada mesin terdaftar. Klik <strong>Daftar mesin baru</strong> untuk menambah perangkat.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($mesin->hasPages())
                <div class="mesin-pager">{{ $mesin->links() }}</div>
            @endif
        </div>
    </div>

    <div class="iot-modal-backdrop" id="editMachineModal">
        <div class="iot-modal">
            <button type="button" class="iot-modal-close" onclick="closeEditMachine()" aria-label="Tutup">×</button>
            <div class="iot-modal-badge" style="background:rgba(15,118,110,.14);color:#0f766e;">EDIT MESIN</div>
            <h3 id="editMachineTitle">Edit Mesin</h3>
            <p>ID mesin tetap (riwayat log & firmware tidak putus). Ubah nama, tipe, dan status aktif.</p>

            <form method="POST" id="editMachineForm" class="mesin-form" style="margin-top:14px;">
                @csrf
                @method('PUT')
                <div class="mesin-form__grid mesin-form__grid--modal">
                    <div class="filter-field">
                        <label class="modal-label">ID Mesin</label>
                        <input id="editIdMesin" type="text" readonly>
                    </div>
                    <div class="filter-field">
                        <label class="modal-label">Nama Mesin</label>
                        <input id="editNamaMesin" type="text" name="nama_mesin" placeholder="contoh: Mesin Absensi Lab A1">
                    </div>
                    <div class="filter-field">
                        <label class="modal-label">Tipe</label>
                        <select id="editTipe" name="tipe" onchange="toggleCustomType(this, 'editCustomType')">
                            @foreach($typeOptions as $typeValue => $typeLabel)
                                <option value="{{ $typeValue }}">{{ $typeLabel }}</option>
                            @endforeach
                            <option value="__custom">+ Tipe baru</option>
                        </select>
                    </div>
                    <div class="filter-field">
                        <label class="modal-label">Nama tipe baru</label>
                        <input id="editCustomType" type="text" name="tipe_custom" placeholder="contoh: pintu" style="display:none;">
                    </div>
                </div>
                <label class="mesin-check">
                    <input id="editIsActive" type="checkbox" name="is_active" value="1">
                    Mesin aktif
                </label>
                <div class="mesin-modal-actions">
                    <button type="button" class="mesin-btn mesin-btn--ghost" onclick="closeEditMachine()">Batal</button>
                    <button type="submit" class="mesin-btn mesin-btn--primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
@endsection

@section('scripts')
<style>
    .mesin-toolbar {
        display: flex; align-items: center; justify-content: space-between; gap: 12px;
        margin-bottom: 14px; padding: 12px 14px; border-radius: 14px;
        background: var(--bg-card); border: 1px solid var(--border-subtle);
        box-shadow: 0 8px 22px rgba(15,23,42,.04);
    }
    [data-theme="dark"] .mesin-toolbar { background: rgba(15,23,42,.72); }
    .mesin-toolbar__left, .mesin-toolbar__right { display: flex; align-items: center; gap: 10px; min-width: 0; flex-wrap: wrap; }
    .mesin-toolbar__meta strong { display: block; font-size: 13.5px; }
    .mesin-toolbar__meta small { display: block; margin-top: 2px; color: var(--text-muted); font-size: 11.5px; }
    .mesin-live {
        display: inline-flex; align-items: center; gap: 7px; padding: 6px 10px; border-radius: 999px;
        background: rgba(2,132,199,.12); color: #0284c7; font-size: 10.5px; font-weight: 800; letter-spacing: .08em;
    }
    .mesin-live__dot { width: 8px; height: 8px; border-radius: 999px; background: currentColor; }

    .mesin-btn {
        display: inline-flex; align-items: center; gap: 7px; min-height: 36px; padding: 0 12px;
        border-radius: 10px; border: 1px solid transparent; text-decoration: none;
        font-size: 12.5px; font-weight: 700; cursor: pointer; white-space: nowrap;
        background: transparent; color: inherit;
    }
    .mesin-btn--sm { min-height: 34px; padding: 0 10px; font-size: 12px; }
    .mesin-btn--primary {
        background: color-mix(in srgb, var(--soft-blue,#4a90d9) 16%, transparent);
        color: var(--soft-blue,#4a90d9);
        border-color: color-mix(in srgb, var(--soft-blue,#4a90d9) 30%, transparent);
    }
    .mesin-btn--ghost {
        background: transparent; color: var(--text-muted); border-color: var(--border-subtle);
    }
    .mesin-btn--ghost:hover { color: var(--text-dark); border-color: color-mix(in srgb, var(--soft-blue,#4a90d9) 35%, var(--border-subtle)); }

    .mesin-kpi {
        display: grid; grid-template-columns: repeat(4, minmax(0,1fr)); gap: 10px; margin-bottom: 12px;
    }
    .mesin-kpi__item {
        display: flex; align-items: center; gap: 12px; padding: 12px 14px; min-height: 76px;
        border-radius: 14px; background: var(--bg-card); border: 1px solid var(--border-subtle);
        border-left: 3px solid var(--kpi,#0284c7); text-decoration: none; color: inherit;
        box-shadow: 0 6px 18px rgba(15,23,42,.035);
        transition: transform .2s ease, border-color .2s ease;
    }
    .mesin-kpi__item:hover { transform: translateY(-2px); border-color: color-mix(in srgb, var(--kpi) 35%, var(--border-subtle)); }
    [data-theme="dark"] .mesin-kpi__item { background: rgba(15,23,42,.72); }
    .mesin-kpi__icon {
        width: 36px; height: 36px; border-radius: 10px; display: grid; place-items: center; flex-shrink: 0;
        background: color-mix(in srgb, var(--kpi) 12%, transparent); color: var(--kpi);
    }
    .mesin-kpi__item span { display: block; font-size: 10.5px; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; color: var(--text-muted); }
    .mesin-kpi__item strong { display: block; margin-top: 1px; font-size: 24px; line-height: 1.1; font-variant-numeric: tabular-nums; }
    .mesin-kpi__item small { display: block; margin-top: 2px; color: var(--text-muted); font-size: 11px; }

    .mesin-type-row { display: none; }
    .mesin-cta {
        display: flex; align-items: center; justify-content: space-between; gap: 14px; flex-wrap: wrap;
        margin-bottom: 12px; padding: 14px 16px; border-radius: 14px;
        background: var(--bg-card); border: 1px solid var(--border-subtle);
        box-shadow: 0 6px 18px rgba(15,23,42,.035);
    }
    [data-theme="dark"] .mesin-cta { background: rgba(15,23,42,.72); }
    .mesin-cta__text { min-width: 0; flex: 1; }
    .mesin-cta__text strong { display: block; font-size: 14px; }
    .mesin-cta__text span { display: block; margin-top: 3px; color: var(--text-muted); font-size: 12px; line-height: 1.45; max-width: 640px; }

    .iot-register { width: min(820px, 100%); }
    .iot-register__grid {
        display: grid; grid-template-columns: minmax(0, 1.2fr) minmax(200px, 0.7fr);
        gap: 16px; align-items: start; margin-top: 4px;
    }
    .mesin-tips--modal {
        padding: 12px; border-radius: 12px; border: 1px solid var(--border-subtle);
        background: color-mix(in srgb, var(--border-subtle) 40%, transparent);
    }
    .mesin-tips__title { display: block; margin-bottom: 10px; font-size: 12.5px; }
    .field-error { display: block; margin-top: 4px; color: #be123c; font-size: 11.5px; font-weight: 600; }
    .input-id-mesin {
        text-transform: uppercase;
        font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
        letter-spacing: .04em;
        font-weight: 700;
    }
    .mesin-list-head__tools { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; justify-content: flex-end; }

    .mesin-provision { display: none; }
    .mesin-card-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; padding: 14px 16px 10px !important; flex-wrap: wrap; }
    .mesin-card-head h3 { margin: 0; font-size: 14.5px; }
    .mesin-card-head > div > span { display: block; margin-top: 3px; color: var(--text-muted); font-size: 11.5px; }
    .mesin-form__grid { display: grid; grid-template-columns: 1fr; gap: 12px; }
    .mesin-form__grid--modal { grid-template-columns: 1fr 1fr; }
    .field-hint { display: block; margin-top: 5px; color: var(--text-muted); font-size: 11px; line-height: 1.35; }
    .filter-field label em { color: #be123c; font-style: normal; }
    .mesin-form__actions { display: flex; align-items: center; gap: 12px; margin-top: 14px; flex-wrap: wrap; }
    .mesin-form__hint { margin: 0; color: var(--text-muted); font-size: 11.5px; max-width: 320px; }

    .mesin-tips__body { display: grid; gap: 11px; }
    .mesin-tips__item { display: flex; gap: 10px; align-items: flex-start; margin-bottom: 10px; }
    .mesin-tips__n {
        width: 26px; height: 26px; border-radius: 8px; display: grid; place-items: center; flex-shrink: 0;
        background: rgba(2,132,199,.12); color: #0284c7; font-size: 12px; font-weight: 800;
    }
    .mesin-tips__item strong { display: inline-flex; align-items: center; gap: 2px; font-size: 12.5px; }
    .mesin-tips__item small { display: block; margin-top: 2px; color: var(--text-muted); font-size: 11.5px; line-height: 1.45; }
    .mesin-tips__item code { font-size: 11px; }
    .mesin-tips__note {
        display: flex; gap: 8px; align-items: flex-start; margin-top: 2px; padding: 10px 12px;
        border-radius: 10px; background: color-mix(in srgb, var(--border-subtle) 55%, transparent);
        color: var(--text-muted); font-size: 11.5px; line-height: 1.45;
    }
    .mesin-tips__note a { color: var(--soft-blue); font-weight: 700; text-decoration: none; }

    .mesin-list-card { overflow: hidden; }
    .mesin-list-head { flex-wrap: wrap; }
    .mesin-filter { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
    .mesin-filter input, .mesin-filter select {
        min-height: 34px; border-radius: 10px; border: 1px solid var(--border-subtle);
        background: var(--bg-main); color: var(--text-dark); padding: 0 10px; font-size: 12.5px;
    }
    .mesin-filter input { min-width: 160px; }
    .mesin-table-wrap { overflow: auto; }
    .mesin-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 12.5px;
        table-layout: fixed;
    }
    .mesin-table thead th {
        text-align: left; padding: 10px 14px; position: sticky; top: 0; z-index: 1;
        background: color-mix(in srgb, var(--bg-card) 88%, #64748b);
        color: var(--text-muted); font-size: 10.5px; font-weight: 800;
        letter-spacing: .05em; text-transform: uppercase; border-bottom: 1px solid var(--border-subtle);
    }
    .mesin-table thead th:nth-child(1) { width: 28%; }
    .mesin-table thead th:nth-child(2) { width: 12%; }
    .mesin-table thead th:nth-child(3) { width: 16%; }
    .mesin-table thead th:nth-child(4) { width: 12%; }
    .mesin-table thead th:nth-child(5) { width: 168px; }
    .mesin-table thead th:nth-child(6) { width: 18%; }
    [data-theme="dark"] .mesin-table thead th { background: rgba(15,23,42,.96); }
    .mesin-table tbody td { padding: 12px 14px; border-bottom: 1px solid var(--border-subtle); vertical-align: middle; }
    .mesin-id-cell { display: flex; align-items: center; gap: 10px; min-width: 0; }
    .mesin-id-cell strong { display: block; font-size: 12.5px; }
    .mesin-id-cell small { display: block; margin-top: 2px; color: var(--text-muted); font-family: ui-monospace, monospace; font-size: 11px; }
    .mesin-dot { width: 9px; height: 9px; border-radius: 999px; flex-shrink: 0; background: #94a3b8; box-shadow: 0 0 0 4px rgba(148,163,184,.14); }
    .mesin-dot--online { background: #059669; box-shadow: 0 0 0 4px rgba(5,150,105,.16); }
    .mesin-dot--offline { background: #d97706; box-shadow: 0 0 0 4px rgba(217,119,6,.16); }
    .mesin-dot--inactive { background: #94a3b8; }
    .mesin-chip {
        display: inline-flex; padding: 3px 8px; border-radius: 999px;
        background: rgba(2,132,199,.12); color: #0284c7; font-size: 11px; font-weight: 700;
    }
    .mesin-badge { display: inline-flex; padding: 3px 8px; border-radius: 999px; font-size: 11px; font-weight: 800; }
    .mesin-badge--online { background: rgba(5,150,105,.12); color: #059669; }
    .mesin-badge--offline { background: rgba(217,119,6,.14); color: #b45309; }
    .mesin-badge--inactive { background: rgba(100,116,139,.14); color: #64748b; }
    .mesin-seen { display: block; margin-top: 4px; color: var(--text-muted); font-size: 11px; }
    .mesin-muted { color: var(--text-muted); }
    .mesin-update-cell { white-space: nowrap; font-variant-numeric: tabular-nums; font-size: 12px; }
    .mesin-aksi-cell { white-space: nowrap; }
    .mesin-actions { display: inline-flex; gap: 6px; justify-content: flex-start; align-items: center; }
    .mesin-actions form { display: inline; margin: 0; }
    .mesin-icon-btn {
        width: 32px; height: 32px; border-radius: 9px; border: 1px solid var(--border-subtle);
        background: color-mix(in srgb, var(--border-subtle) 55%, transparent);
        color: var(--text-muted); display: inline-grid; place-items: center; cursor: pointer;
    }
    .mesin-icon-btn:hover { color: var(--text-dark); border-color: color-mix(in srgb, #0284c7 35%, var(--border-subtle)); }
    .mesin-icon-btn--danger:hover { color: #be123c; border-color: rgba(190,18,60,.35); }
    .mesin-empty { text-align: center; color: var(--text-muted); padding: 32px 14px !important; }
    .mesin-pager { padding: 14px 16px; border-top: 1px solid var(--border-subtle); }

    .iot-modal-backdrop {
        position: fixed; inset: 0; z-index: 3000; display: none; align-items: center; justify-content: center;
        padding: 18px; background: rgba(15, 23, 42, 0.72); backdrop-filter: blur(8px);
    }
    .iot-modal-backdrop.is-open { display: flex; }
    .iot-modal {
        width: min(720px, 100%); position: relative; border: 1px solid rgba(148,163,184,0.28);
        border-radius: 18px; padding: 22px; background: var(--bg-card); color: var(--text-dark);
        box-shadow: 0 24px 70px rgba(15,23,42,0.38); max-height: min(92vh, 900px); overflow: auto;
    }
    .iot-wizard { width: min(760px, 100%); }
    .iot-modal h3 { margin: 0 0 8px; font-size: 20px; }
    .iot-modal p { color: var(--text-muted); font-size: 13px; line-height: 1.6; margin: 0 0 14px; }
    .iot-modal-close {
        position: absolute; top: 14px; right: 14px; width: 34px; height: 34px; border: 0; border-radius: 999px;
        background: rgba(148,163,184,0.14); color: var(--text-dark); font-size: 20px; cursor: pointer;
    }
    .iot-modal-badge {
        display: inline-flex; padding: 6px 10px; border-radius: 999px;
        background: rgba(245,158,11,0.16); color: #b45309;
        font-size: 11px; font-weight: 800; letter-spacing: 0.08em;
    }

    .wiz-toast {
        display: flex; align-items: center; gap: 12px; margin: 0 36px 16px 0; padding: 12px 14px;
        border-radius: 12px; background: rgba(5,150,105,.12); border: 1px solid rgba(5,150,105,.28);
        animation: wizIn .35s ease;
    }
    .wiz-toast__icon { color: #059669; display: grid; place-items: center; }
    .wiz-toast strong { display: block; font-size: 13.5px; color: #059669; }
    .wiz-toast small { display: block; margin-top: 2px; color: var(--text-muted); font-size: 11.5px; }
    @keyframes wizIn { from { opacity: 0; transform: translateY(-6px); } to { opacity: 1; transform: none; } }

    .wiz-steps {
        display: grid; grid-template-columns: repeat(4, minmax(0,1fr)); gap: 8px; margin-bottom: 16px;
    }
    .wiz-step {
        display: flex; align-items: center; gap: 8px; padding: 9px 10px; border-radius: 10px;
        border: 1px solid var(--border-subtle); background: transparent; color: var(--text-muted);
        font-size: 11.5px; font-weight: 700; cursor: pointer; text-align: left;
    }
    .wiz-step span {
        width: 22px; height: 22px; border-radius: 999px; display: grid; place-items: center; flex-shrink: 0;
        background: rgba(148,163,184,.16); font-size: 11px;
    }
    .wiz-step.is-active, .wiz-step.is-done {
        border-color: color-mix(in srgb, #0284c7 40%, var(--border-subtle));
        color: var(--text-dark); background: rgba(2,132,199,.08);
    }
    .wiz-step.is-active span, .wiz-step.is-done span { background: #0284c7; color: #fff; }
    .wiz-step.is-done span { background: #059669; }

    .wiz-panel { display: none; }
    .wiz-panel.is-active { display: block; animation: wizIn .28s ease; }
    .wiz-meta {
        display: grid; grid-template-columns: repeat(2, minmax(0,1fr)); gap: 10px; margin-bottom: 16px;
    }
    .wiz-meta > div {
        padding: 10px 12px; border-radius: 12px; border: 1px solid var(--border-subtle);
        background: color-mix(in srgb, var(--border-subtle) 40%, transparent);
    }
    .wiz-meta span { display: block; font-size: 10.5px; color: var(--text-muted); text-transform: uppercase; letter-spacing: .05em; font-weight: 700; }
    .wiz-meta strong { display: block; margin-top: 4px; font-size: 13.5px; word-break: break-all; }

    .api-copy-row { display: flex; gap: 10px; }
    .api-copy-row input {
        width: 100%; padding: 12px; border: 1px solid var(--border-subtle); border-radius: 12px;
        background: var(--bg-main); color: var(--text-dark); font-family: ui-monospace, monospace; font-size: 12px;
    }
    .api-copy-row button {
        white-space: nowrap; border: 0; border-radius: 10px; padding: 0 16px;
        background: #0284c7; color: #fff; font-weight: 800; cursor: pointer;
    }
    .wiz-warn {
        display: flex; gap: 8px; align-items: flex-start; margin-top: 12px; padding: 10px 12px;
        border-radius: 10px; background: rgba(217,119,6,.12); color: #b45309; font-size: 12px;
    }
    .wiz-code-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
    .wiz-code {
        border: 1px solid var(--border-subtle); border-radius: 12px; overflow: hidden;
        background: color-mix(in srgb, var(--border-subtle) 35%, transparent);
    }
    .wiz-code--full { grid-column: 1 / -1; }
    .wiz-code__head {
        display: flex; align-items: center; justify-content: space-between; gap: 8px;
        padding: 8px 10px; border-bottom: 1px solid var(--border-subtle); font-size: 11.5px; font-weight: 700;
    }
    .wiz-code code, .wiz-code pre {
        display: block; margin: 0; padding: 12px; font-family: ui-monospace, monospace;
        font-size: 12px; line-height: 1.5; white-space: pre-wrap; word-break: break-all; color: var(--text-dark);
    }
    .wiz-checklist { margin: 0 0 16px; padding-left: 18px; color: var(--text-muted); font-size: 13px; line-height: 1.7; }
    .wiz-checklist code { font-size: 12px; }
    .wiz-actions { display: flex; justify-content: flex-end; gap: 10px; flex-wrap: wrap; margin-top: 8px; }
    .wiz-actions--end { justify-content: flex-end; }

    /* Toast/confirm harus di atas wizard (z-index 3000) */
    .swal2-container { z-index: 4200 !important; }

    .help-q {
        display: inline-grid; place-items: center; width: 18px; height: 18px; margin-left: 6px;
        border: 1px solid color-mix(in srgb, #0284c7 45%, var(--border-subtle));
        border-radius: 999px; background: rgba(2,132,199,.12); color: #0284c7;
        font-size: 11px; font-weight: 800; line-height: 1; cursor: pointer; vertical-align: middle;
        padding: 0; flex-shrink: 0;
    }
    .help-q:hover { background: #0284c7; color: #fff; border-color: #0284c7; }
    .help-q--sm { width: 16px; height: 16px; font-size: 10px; margin-left: 4px; }
    label .help-q { margin-left: 4px; }
    h3 .help-q { margin-left: 8px; vertical-align: middle; }

    #firmwareHelpModal { z-index: 3600; }
    .iot-help { width: min(680px, 100%); }
    .iot-help__desc { margin-bottom: 12px !important; }
    .iot-help__img-wrap {
        border: 1px solid var(--border-subtle); border-radius: 12px; overflow: hidden;
        background: #0f172a; margin-bottom: 12px;
    }
    .iot-help__img {
        display: block; width: 100%; height: auto; max-height: min(52vh, 420px);
        object-fit: contain; background: #0f172a;
    }
    .iot-help__img-wrap.is-hidden { display: none; }
    .iot-help__code {
        font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
        font-size: 12px; line-height: 1.55; padding: 12px 14px; border-radius: 12px;
        border: 1px solid var(--border-subtle);
        background: color-mix(in srgb, var(--border-subtle) 40%, transparent);
        color: var(--text-dark); white-space: pre-wrap; word-break: break-word;
        margin-bottom: 4px;
    }
    .iot-help__code:empty { display: none; }
    .iot-help__code code { font-size: 12px; }

    .modal-label { display: block; font-size: 12px; font-weight: 800; margin-bottom: 6px; color: var(--text-label); }
    .mesin-check { display: flex; align-items: center; gap: 10px; margin-top: 12px; font-weight: 700; color: var(--text-label); }
    .mesin-modal-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 16px; }

    @media (max-width: 1100px) {
        .mesin-kpi { grid-template-columns: 1fr 1fr; }
        .wiz-steps { grid-template-columns: 1fr 1fr; }
        .iot-register__grid { grid-template-columns: 1fr; }
    }
    @media (max-width: 720px) {
        .mesin-toolbar { flex-direction: column; align-items: stretch; }
        .mesin-kpi { grid-template-columns: 1fr; }
        .mesin-form__grid, .mesin-form__grid--modal, .wiz-meta, .wiz-code-grid { grid-template-columns: 1fr; }
        .api-copy-row { flex-direction: column; }
        .api-copy-row button { padding: 12px; }
        .wiz-steps { grid-template-columns: 1fr; }
        .mesin-cta { flex-direction: column; align-items: stretch; }
    }
</style>
<script>
    function toastOk(title) {
        if (typeof Swal === 'undefined') return;
        Swal.fire({
            toast: true, position: 'top-end', icon: 'success',
            title: title || 'Tersalin', showConfirmButton: false, timer: 1800, timerProgressBar: true
        });
    }

    function copyRaw(text, msg) {
        navigator.clipboard.writeText(String(text || '')).then(() => toastOk(msg));
    }

    function copyText(inputId, msg) {
        const input = document.getElementById(inputId);
        if (!input) return;
        input.select();
        input.setSelectionRange(0, 99999);
        copyRaw(input.value, msg);
    }

    function wizGo(step) {
        const n = Number(step) || 1;
        document.querySelectorAll('.wiz-step').forEach((el) => {
            const s = Number(el.dataset.step);
            el.classList.toggle('is-active', s === n);
            el.classList.toggle('is-done', s < n);
        });
        document.querySelectorAll('.wiz-panel').forEach((el) => {
            el.classList.toggle('is-active', Number(el.dataset.panel) === n);
        });
        if (window.refreshLucideIcons) window.refreshLucideIcons();
    }

    function closeSetupWizard(confirmed) {
        const modal = document.getElementById('setupWizardModal');
        if (!modal) return;
        if (!confirmed) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'warning',
                    title: 'Tutup setup?',
                    text: 'Pastikan API key sudah disalin. Key tidak bisa dilihat ulang setelah ini.',
                    showCancelButton: true,
                    confirmButtonText: 'Ya, tutup',
                    cancelButtonText: 'Kembali',
                    confirmButtonColor: '#0284c7'
                }).then((r) => {
                    if (r.isConfirmed) {
                        modal.classList.remove('is-open');
                        modal.style.display = 'none';
                    }
                });
                return;
            }
        }
        modal.classList.remove('is-open');
        modal.style.display = 'none';
        if (confirmed) toastOk('Setup selesai — lanjut flash firmware');
    }

    function forceUpperId(el) {
        if (!el) return;
        const start = el.selectionStart;
        const end = el.selectionEnd;
        el.value = String(el.value || '')
            .toUpperCase()
            .replace(/\s+/g, '-')
            .replace(/[^A-Z0-9_-]/g, '');
        if (typeof start === 'number' && typeof end === 'number') {
            try { el.setSelectionRange(start, end); } catch (e) {}
        }
    }

    function openRegisterModal() {
        const modal = document.getElementById('registerMachineModal');
        if (!modal) return;
        modal.style.display = 'flex';
        modal.classList.add('is-open');
        const first = modal.querySelector('input[name="id_mesin"]');
        if (first) setTimeout(() => first.focus(), 50);
        if (window.refreshLucideIcons) window.refreshLucideIcons();
    }

    function closeRegisterModal() {
        const modal = document.getElementById('registerMachineModal');
        if (!modal) return;
        modal.style.display = 'none';
        modal.classList.remove('is-open');
    }

    const FIRMWARE_HELP = {
        id: {
            title: 'ID mesin di firmware ESP32',
            desc: 'ID mesin di form harus sama persis dengan #define ID_MESIN di program — wajib huruf besar.',
            img: @json(asset('images/help/firmware-id-apikey.png')),
            code: '#define ID_MESIN "ABSEN-LAB-A1"\n\n→ nilai ini = kolom ID Mesin di form daftar'
        },
        apikey: {
            title: 'API key di firmware ESP32',
            desc: 'Setelah simpan, API key muncul sekali. Salin lalu tempel ke #define API_KEY di config firmware.',
            img: @json(asset('images/help/firmware-id-apikey.png')),
            code: '#define API_KEY "X4w0jL3QRewVGsbwAbuVrCWH4gfwR1ZQ84UFZD16"\n\n→ nilai ini = API key dari wizard setelah daftar mesin'
        },
        config: {
            title: 'Contoh config.h ESP32',
            desc: 'File konfigurasi biasanya berisi WiFi, API_BASE, ID_MESIN, API_KEY, dan PIN menu.',
            img: @json(asset('images/help/firmware-config.png')),
            code: 'WIFI_SSID / WIFI_PASSWORD  → jaringan lab\nAPI_BASE                  → URL server SIMALAS\nID_MESIN                  → sama dengan form\nAPI_KEY                   → dari wizard setelah simpan'
        },
        nama: {
            title: 'Nama mesin (bukan di firmware)',
            desc: 'Nama hanya untuk tampilan di daftar & monitoring. Tidak perlu ditulis di ESP32 — yang wajib di firmware adalah ID_MESIN.',
            img: '',
            code: 'Form web:  Nama mesin = "Mesin Absensi Lab A1"\nFirmware:  #define ID_MESIN "ABSEN-LAB-A1"\n\nNama bebas (campur huruf). ID wajib huruf besar.'
        },
        tipe: {
            title: 'Tipe perangkat',
            desc: 'Menentukan endpoint & fitur yang dipakai mesin (Absensi, Loker, atau tipe custom). Pilih sesuai fungsi hardware.',
            img: '',
            code: 'Absensi  → heartbeat, tap fingerprint\nLoker    → heartbeat, buka/tutup loker\nCustom   → buat tipe sendiri jika perangkat baru'
        }
    };

    function openFirmwareHelp(kind) {
        const data = FIRMWARE_HELP[kind] || FIRMWARE_HELP.config;
        const modal = document.getElementById('firmwareHelpModal');
        const title = document.getElementById('firmwareHelpTitle');
        const desc = document.getElementById('firmwareHelpDesc');
        const img = document.getElementById('firmwareHelpImg');
        const code = document.getElementById('firmwareHelpCode');
        const wrap = img ? img.closest('.iot-help__img-wrap') : null;
        if (!modal || !title || !desc || !img || !code) return;

        title.textContent = data.title;
        desc.textContent = data.desc;
        code.textContent = data.code || '';
        if (data.img) {
            img.src = data.img;
            img.alt = data.title;
            if (wrap) wrap.classList.remove('is-hidden');
        } else {
            img.removeAttribute('src');
            if (wrap) wrap.classList.add('is-hidden');
        }
        modal.style.display = 'flex';
        modal.classList.add('is-open');
    }

    function closeFirmwareHelp() {
        const modal = document.getElementById('firmwareHelpModal');
        if (!modal) return;
        modal.style.display = 'none';
        modal.classList.remove('is-open');
    }

    function toggleCustomType(selectEl, inputId) {
        const input = document.getElementById(inputId);
        if (!input) return;
        const custom = selectEl.value === '__custom';
        input.style.display = custom ? 'block' : 'none';
        input.required = custom;
        if (!custom) input.value = '';
    }

    function openEditMachine(button) {
        const modal = document.getElementById('editMachineModal');
        const form = document.getElementById('editMachineForm');
        const idInput = document.getElementById('editIdMesin');
        const nameInput = document.getElementById('editNamaMesin');
        const typeSelect = document.getElementById('editTipe');
        const activeInput = document.getElementById('editIsActive');
        const title = document.getElementById('editMachineTitle');
        if (!modal || !form || !idInput || !nameInput || !typeSelect || !activeInput || !title) return;

        form.action = button.dataset.updateUrl;
        idInput.value = button.dataset.idMesin || '';
        nameInput.value = button.dataset.namaMesin || '';
        activeInput.checked = button.dataset.active === '1';
        title.textContent = `Edit Mesin ${button.dataset.idMesin || ''}`;

        const type = button.dataset.tipe || 'absensi';
        const exists = Array.from(typeSelect.options).some((option) => option.value === type);
        typeSelect.value = exists ? type : '__custom';
        const customInput = document.getElementById('editCustomType');
        if (customInput) customInput.value = exists ? '' : type;
        toggleCustomType(typeSelect, 'editCustomType');

        modal.style.display = 'flex';
        if (window.refreshLucideIcons) window.refreshLucideIcons();
    }

    function closeEditMachine() {
        const modal = document.getElementById('editMachineModal');
        if (modal) modal.style.display = 'none';
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.wiz-step').forEach((btn) => {
            btn.addEventListener('click', () => wizGo(btn.dataset.step));
        });

        const form = document.getElementById('mesinCreateForm');
        const submitBtn = document.getElementById('mesinCreateSubmit');
        if (form && submitBtn) {
            form.addEventListener('submit', function () {
                submitBtn.disabled = true;
                submitBtn.textContent = 'Menyimpan…';
            });
        }

        const regModal = document.getElementById('registerMachineModal');
        if (regModal && regModal.style.display === 'flex') {
            regModal.classList.add('is-open');
        }

        if (document.getElementById('setupWizardModal')) {
            wizGo(1);
            toastOk(@json($setupMode === 'regenerate' ? 'API key baru digenerate' : 'Mesin berhasil ditambahkan'));
        }

        const helpModal = document.getElementById('firmwareHelpModal');
        if (helpModal) {
            helpModal.addEventListener('click', (e) => {
                if (e.target === helpModal) closeFirmwareHelp();
            });
        }
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                const hm = document.getElementById('firmwareHelpModal');
                if (hm && hm.classList.contains('is-open')) closeFirmwareHelp();
            }
        });

        if (window.refreshLucideIcons) window.refreshLucideIcons();
    });
</script>
@endsection
