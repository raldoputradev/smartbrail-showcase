@extends('layouts.student')

@section('title', 'Masuk Mahasiswa')

@section('content')
<div class="student-auth">
    <section class="student-auth__intro">
        <span class="student-eyebrow">Smart BRAIL · Absensi laboratorium</span>
        <h1>Portal Mahasiswa</h1>
        <p>Masuk untuk melihat absensi, tim PBL, notifikasi, dan status enrollment Anda sendiri.</p>
        <ul class="student-checklist">
            <li>Data pribadi dari backend Smart BRAIL yang sama</li>
            <li>Aktivasi akun import dibantu petugas</li>
            <li>Absensi tetap dilakukan melalui mesin fingerprint</li>
        </ul>
    </section>
    <section class="student-auth__card" aria-labelledby="login-title">
        <h2 id="login-title">Masuk akun</h2>
        <p class="student-muted">Gunakan NIM dan password Anda, bukan akun staf.</p>
        <form method="POST" action="{{ route('mahasiswa.login.post') }}" class="student-form">
            @csrf
            <label for="nitk_nidn">NIM</label>
            <input id="nitk_nidn" name="nitk_nidn" type="text" value="{{ old('nitk_nidn') }}" required autofocus autocomplete="username" inputmode="text" maxlength="50">
            @error('nitk_nidn')<span class="student-field-error">{{ $message }}</span>@enderror

            <label for="password">Password</label>
            <input id="password" name="password" type="password" required autocomplete="current-password" maxlength="128">
            @error('password')<span class="student-field-error">{{ $message }}</span>@enderror

            <label class="student-checkbox"><input type="checkbox" name="remember" value="1" @checked(old('remember'))> Ingat saya di perangkat ini</label>
            <button type="submit" class="student-button student-button--primary" data-loading="Memeriksa akun…">Masuk portal</button>
        </form>
        <div class="student-auth__links">
            <a href="{{ route('activation.show') }}">Aktivasi akun import</a>
            <a href="{{ route('forgot-password') }}">Lupa password?</a>
        </div>
        <p class="student-auth__note">Petugas tidak mengetahui password permanen Anda. Jangan bagikan password atau kode OTP.</p>
    </section>
</div>
@endsection
