@extends('layouts.dashboard')

@php
    $applied = $payload['state'] === 'applied';
    $t = $payload['totals'];
@endphp

@section('title', $applied ? 'Hasil Import Akun' : 'Pratinjau Import Akun')
@section('page-title', $applied ? 'Hasil Import Akun' : 'Pratinjau Import Akun')
@section('page-subtitle', $typeLabel.' · '.$payload['file_name'])

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
                    <li class="is-on"><b>2</b>Pratinjau</li>
                    <li class="{{ $applied ? 'is-on' : '' }}"><b>3</b>Konfirmasi</li>
                    <li class="{{ $applied ? 'is-on' : '' }}"><b>4</b>Laporan &amp; kartu aktivasi</li>
                </ol>
                <div class="imp-row">
                    <a href="{{ route('admin.users.import', ['jenis' => $payload['type']]) }}" class="imp-btn">Unggah file lain</a>
                    <a href="{{ route('admin.users', ['verify_status' => 'menunggu_aktivasi']) }}" class="imp-btn">Akun menunggu aktivasi</a>
                </div>
            </div>
        </div>

        @if(session('success'))
            <div class="imp-flash imp-flash--ok animate-in">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="imp-flash imp-flash--err animate-in" role="alert">{{ session('error') }}</div>
        @endif
        @foreach($payload['notices'] as $notice)
            <div class="imp-flash imp-flash--info animate-in">{{ $notice }}</div>
        @endforeach

        <div class="imp-card animate-in">
            <h3>{{ $applied ? 'Ringkasan hasil' : 'Ringkasan pratinjau' }}</h3>
            <p class="imp-sub">
                @if($applied)
                    Diterapkan {{ \Illuminate\Support\Carbon::parse($payload['applied_at'])->timezone(config('app.timezone'))->format('d/m/Y H:i') }}.
                    Total {{ $t['total'] }} baris = {{ $t['dibuat'] }} dibuat + {{ $t['duplikat'] }} duplikat + {{ $t['gagal'] }} gagal + {{ $t['dilewati'] }} dilewati.
                    @if($t['dibuat'] > 0 && ($t['duplikat'] + $t['gagal']) > 0)
                        <strong>Sukses sebagian:</strong> baris yang dibuat tetap tersimpan; perbaiki baris gagal lalu unggah ulang — baris yang sudah dibuat akan tercatat sebagai duplikat, bukan ditimpa.
                    @endif
                @else
                    Belum ada akun yang dibuat. Total {{ $t['total'] }} baris = {{ $t['siap'] }} siap + {{ $t['duplikat'] }} duplikat + {{ $t['gagal'] }} gagal + {{ $t['dilewati'] }} dilewati.
                    Hanya baris "Siap dibuat" yang diproses saat konfirmasi; validasi diulang per baris.
                @endif
            </p>
            <div class="imp-totals">
                <div class="imp-total"><strong>{{ $applied ? $t['dibuat'] : $t['siap'] }}</strong><span>{{ $applied ? 'Dibuat' : 'Siap dibuat' }}</span></div>
                <div class="imp-total"><strong>{{ $t['duplikat'] }}</strong><span>Duplikat</span></div>
                <div class="imp-total"><strong>{{ $t['gagal'] }}</strong><span>Gagal validasi</span></div>
                <div class="imp-total"><strong>{{ $t['dilewati'] }}</strong><span>Dilewati</span></div>
                <div class="imp-total"><strong>{{ $t['total'] }}</strong><span>Total baris</span></div>
            </div>

            <div class="imp-row" style="margin-top:14px;">
                @if(!$applied)
                    @if($t['siap'] > 0 && $canApply)
                        <form method="POST" action="{{ route('admin.users.import.apply', $payload['token']) }}"
                              onsubmit="this.querySelector('button').disabled=true;">
                            @csrf
                            <button type="submit" class="imp-btn imp-btn--primary">Buat {{ $t['siap'] }} akun (menunggu aktivasi)</button>
                        </form>
                    @else
                        <span class="imp-muted">Tidak ada baris yang bisa dibuat.</span>
                    @endif
                @endif
                <a class="imp-btn" href="{{ route('admin.users.import.report', $payload['token']) }}">Unduh laporan {{ $applied ? 'hasil' : 'pratinjau' }} (.csv)</a>
            </div>
        </div>

        @if($applied && $t['dibuat'] > 0)
            <div class="imp-card animate-in">
                <h3>Kartu aktivasi</h3>
                @if($cardsAvailable)
                    <div class="imp-flash imp-flash--warn" style="margin-bottom:12px;">
                        Kartu berisi kode aktivasi rahasia dan <strong>hanya bisa diunduh sekali</strong> (tersedia {{ \App\Support\AccountImport\AccountImportService::CARDS_TTL_MINUTES }} menit setelah import).
                        Bagikan per orang secara pribadi. Kode tidak tersimpan di laporan maupun log; SIMALAS hanya menyimpan hash-nya.
                    </div>
                    <form method="POST" action="{{ route('admin.users.import.cards', $payload['token']) }}"
                          onsubmit="const b=this.querySelector('button');setTimeout(()=>{b.disabled=true;b.textContent='Kartu sudah diunduh';},50);">
                        @csrf
                        <button type="submit" class="imp-btn imp-btn--warn">Unduh kartu aktivasi (.csv, sekali)</button>
                    </form>
                @else
                    <p class="imp-sub" style="margin:0;">
                        Kartu aktivasi sudah diunduh{{ !empty($payload['cards_downloaded_at']) ? ' pada '.\Illuminate\Support\Carbon::parse($payload['cards_downloaded_at'])->timezone(config('app.timezone'))->format('d/m/Y H:i') : '' }} atau sudah kedaluwarsa.
                        Jika kode hilang, buat kode baru per akun dari Manajemen User (tombol kunci) — kode lama otomatis batal.
                    </p>
                @endif
            </div>
        @endif

        <div class="imp-card animate-in">
            <h3>Detail per baris</h3>
            <p class="imp-sub">Nomor baris mengikuti file. Alasan ditulis per baris; tidak ada password atau kode di tabel ini.</p>
            <div class="imp-table-wrap">
                <table class="imp-table">
                    <thead>
                    <tr>
                        <th>Baris</th>
                        <th>{{ $idLabel }}</th>
                        <th>Nama</th>
                        <th>Status</th>
                        <th>Alasan</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($payload['rows'] as $row)
                        <tr>
                            <td>{{ $row['line'] }}</td>
                            <td><code>{{ $row['identifier'] !== '' ? $row['identifier'] : '—' }}</code></td>
                            <td>{{ $row['nama'] !== '' ? $row['nama'] : '—' }}</td>
                            <td><span class="imp-pill imp-pill--{{ $row['status'] }}">{{ $labels[$row['status']] ?? $row['status'] }}</span></td>
                            <td>{{ $row['reason'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="imp-muted">File tidak berisi baris data.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
