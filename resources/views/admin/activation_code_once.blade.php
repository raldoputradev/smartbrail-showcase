@extends('layouts.dashboard')

@php
    $isStudent = $target->role === 'mahasiswa';
@endphp

@section('title', $isStudent ? 'Password Sementara Mahasiswa' : 'Kode Aktivasi Baru')
@section('page-title', $isStudent ? 'Password Sementara Mahasiswa' : 'Kode Aktivasi Baru')
@section('page-subtitle', 'Ditampilkan sekali — kredensial lama sudah tidak berlaku')

@section('sidebar-nav')
    @include('admin._sidebar')
@endsection

@section('content')
    @include('admin.partials.import-styles')

    <div class="imp-wrap" style="max-width:640px;">
        <div class="imp-card animate-in">
            <h3>{{ $target->nama_lengkap }}</h3>
            <p class="imp-sub">
                {{ ucfirst($target->role) }} · ID login <code>{{ $target->nitk_nidn }}</code> · berlaku sampai {{ $expiresAt->timezone(config('app.timezone'))->format('d/m/Y H:i') }}
            </p>
            <div class="imp-code" aria-label="{{ $isStudent ? 'Password sementara' : 'Kode aktivasi' }}">{{ $code }}</div>
            <div class="imp-flash imp-flash--warn" style="margin-top:14px;">
                Salin atau catat sekarang. Kode ini tidak disimpan dalam bentuk asli, tidak masuk log, dan tidak bisa ditampilkan lagi.
                Memuat ulang halaman ini akan membuat kode baru lagi.
            </div>
            @if($isStudent)
                <p class="imp-muted" style="margin-top:12px;">
                    Mahasiswa langsung masuk melalui halaman login <strong>{{ route('login') }}</strong> menggunakan NIM <code>{{ $target->nitk_nidn }}</code> dan password sementara di atas, lalu diwajibkan membuat password pribadi baru sebelum masuk ke beranda.
                </p>
                <div class="imp-row" style="margin-top:12px;">
                    <a href="{{ route('admin.users') }}" class="imp-btn imp-btn--primary">Selesai</a>
                </div>
            @else
                <p class="imp-muted" style="margin-top:12px;">
                    Pemilik membuka <strong>{{ $activationUrl }}</strong>, memasukkan ID login dan kode ini, lalu membuat password,
                    mengisi email &amp; nomor HP, dan memverifikasi email. Setelah itu akun aktif penuh tanpa persetujuan admin tambahan.
                </p>
                <div class="imp-row" style="margin-top:12px;">
                    <a href="{{ route('admin.users', ['verify_status' => 'menunggu_aktivasi']) }}" class="imp-btn imp-btn--primary">Selesai</a>
                </div>
            @endif
        </div>
    </div>
@endsection
