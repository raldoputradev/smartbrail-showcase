@extends('layouts.dashboard')

@section('title', 'Import Akun')
@section('page-title', 'Import Akun')
@section('page-subtitle', 'Tambah banyak akun dari .xlsx / .csv — pemilik akun mengaktifkan sendiri')

@section('sidebar-nav')
    @include('admin._sidebar')
@endsection

@section('content')
    @include('admin.partials.import-styles')

    <div class="imp-wrap">
        <div class="imp-card animate-in">
            <div class="imp-row" style="justify-content:space-between;">
                <ol class="imp-steps">
                    <li class="is-on"><b>1</b>Unggah</li>
                    <li><b>2</b>Pratinjau</li>
                    <li><b>3</b>Konfirmasi</li>
                    <li><b>4</b>Laporan &amp; kartu aktivasi</li>
                </ol>
                <a href="{{ route('admin.users') }}" class="imp-btn">Kembali ke Manajemen User</a>
            </div>
        </div>

        @if($errors->any())
            <div class="imp-flash imp-flash--err animate-in" role="alert">{{ $errors->first() }}</div>
        @endif

        <div class="imp-card animate-in">
            <h3>Unggah file</h3>
            <p class="imp-sub">
                Import hanya membuat akun baru berstatus <strong>menunggu aktivasi</strong>. Email, nomor HP, dan password tidak diisi admin —
                pemilik akun mengisinya sendiri di halaman <strong>Aktivasi Akun</strong> memakai kode aktivasi (berlaku {{ $ttlDays }} hari, sekali pakai).
                Akun yang sudah terdaftar tidak diubah. Pratinjau belum menyimpan apa pun.
            </p>
            <form method="POST" action="{{ route('admin.users.import.preview') }}" enctype="multipart/form-data">
                @csrf
                <div class="imp-row" style="align-items:flex-end;">
                    <label class="imp-field" style="max-width:260px;">
                        <span>Jenis akun</span>
                        <select name="jenis" required>
                            @foreach($types as $key => $type)
                                <option value="{{ $key }}" @selected(old('jenis', $selected) === $key) @disabled(!$type['allowed'])>
                                    {{ $type['label'] }}{{ $type['allowed'] ? '' : ' (tidak berwenang)' }}
                                </option>
                            @endforeach
                        </select>
                    </label>
                    <label class="imp-field">
                        <span>File .xlsx atau .csv (maks 2 MB, {{ number_format(\App\Support\AccountImport\TabularFileReader::MAX_DATA_ROWS) }} baris)</span>
                        <input type="file" name="file" accept=".xlsx,.csv" required>
                    </label>
                    <button type="submit" class="imp-btn imp-btn--primary">Pratinjau</button>
                </div>
            </form>
        </div>

        <div class="imp-card animate-in">
            <h3>Template resmi</h3>
            <p class="imp-sub">
                Satu sheet, baris pertama header. Kolom identitas berformat Teks agar NIM/NIDN/NITK tidak berubah (mis. angka nol di depan).
                Formula ditolak, file .xls/.xlsm ditolak. Kolom <code>role</code> opsional dan hanya dicocokkan dengan jenis import — tidak memberi hak akses.
            </p>
            <div class="imp-cols">
                @foreach($types as $key => $type)
                    <div class="imp-col-card">
                        <h4>{{ $type['label'] }}</h4>
                        <ul>
                            @foreach($type['columns'] as $name => [$required, $desc])
                                <li><code>{{ $name }}</code>{{ $required ? ' *' : '' }} — {{ $desc }}</li>
                            @endforeach
                        </ul>
                        @if($type['allowed'])
                            <div class="imp-row">
                                <a class="imp-btn" href="{{ route('admin.users.import.template', ['type' => $key, 'format' => 'xlsx']) }}">Template .xlsx</a>
                                <a class="imp-btn" href="{{ route('admin.users.import.template', ['type' => $key, 'format' => 'csv']) }}">Template .csv</a>
                            </div>
                        @else
                            <p class="imp-muted">Anda tidak berwenang membuat akun jenis ini.</p>
                        @endif
                    </div>
                @endforeach
            </div>
            <p class="imp-muted" style="margin-top:12px;">
                * wajib. Kolom email/no_hp/password/status/finger_id di file akan diabaikan dan disebutkan di pratinjau.
                CSV harus UTF-8 (pemisah koma, titik koma, atau tab).
            </p>
        </div>
    </div>
@endsection
