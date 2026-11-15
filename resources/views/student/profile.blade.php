@extends('layouts.student')

@section('title', 'Profil & Keamanan Akun')
@section('page-title', 'Profil Saya')

@section('content')
    @php
        $initials = collect(explode(' ', trim((string) $user->nama_lengkap)))
            ->filter()
            ->take(2)
            ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('') ?: 'M';
        $photoUrl = $user->profile_photo_url;
        $hasEmail = filled($user->email);
        $isEmailVerified = $hasEmail && $user->email_verified_at !== null;
        $hasPhone = filled($user->no_hp);
        $hasFingerId = filled($user->finger_id);
        $hasFingerTemplate = ! empty($user->finger_template);
        $isActive = (bool) ($user->is_active ?? true);
    @endphp

    <div class="sp-page">
        {{-- Pagehead foto kampus + identitas utama --}}
        <section class="sp-pagehead" aria-label="Identitas utama">
            <div class="spp-head" style="border:0;background:transparent;padding:6px 0 0">
                <div class="spp-head__photo">
                    <span class="sp-avatar sp-avatar--xl">
                        @if($photoUrl)
                            <img src="{{ $photoUrl }}" alt="Foto {{ $user->nama_lengkap }}" onerror="this.remove()">
                        @endif
                        <span>{{ $initials }}</span>
                    </span>
                    <form method="POST" action="{{ route('profiles.photo.update') }}" enctype="multipart/form-data" id="sppPhotoForm">
                        @csrf
                        <label class="spp-head__cam" title="Unggah foto profil baru" role="button" tabindex="0">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                            <input type="file" name="photo" accept="image/png,image/jpeg,image/webp" hidden onchange="this.form.submit()">
                        </label>
                    </form>
                </div>
                <div class="spp-head__info">
                    <h2 class="spp-head__name">{{ $user->nama_lengkap }}</h2>
                    <p class="spp-head__sub">NIM <strong class="sp-mono">{{ $user->nitk_nidn }}</strong> &bull; {{ $prodiName ?: 'Program Studi belum diatur' }}</p>
                    <div class="spp-head__badges">
                        <span class="sp-status sp-status--{{ $isActive ? 'ok' : 'neutral' }}">{{ $isActive ? 'Mahasiswa Aktif' : 'Nonaktif' }}</span>
                        <span class="sp-status {{ $isEmailVerified ? 'sp-status--ok' : ($hasEmail ? 'sp-status--warn' : 'sp-status--neutral') }}">
                            {{ $isEmailVerified ? 'Email Terverifikasi (OTP)' : ($hasEmail ? 'Email Belum Verifikasi' : 'Email Belum Diisi') }}
                        </span>
                        <span class="sp-status {{ $hasPhone ? 'sp-status--info' : 'sp-status--neutral' }}">
                            {{ $hasPhone ? 'No. HP Tersimpan' : 'No. HP Belum Diisi' }}
                        </span>
                    </div>
                </div>
                <div class="spp-head__actions" style="display:flex;gap:8px;align-items:flex-start">
                    @if($user->profile_photo_path)
                        <form method="POST" action="{{ route('profiles.photo.destroy') }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="sp-btn sp-btn--ghost sp-btn--sm" style="color:var(--sp-danger)">Hapus Foto</button>
                        </form>
                    @endif
                    <a href="{{ route('mahasiswa.password.change') }}" class="sp-btn sp-btn--primary sp-btn--sm">Ganti Password</a>
                </div>
            </div>
        </section>

        {{-- Grid 2 kolom: Identitas akademik & Kontak pemulihan --}}
        <div class="sp-grid--2cols">
            <section class="sp-card" aria-labelledby="spAkademikTitle">
                <div class="sp-card__head">
                    <div>
                        <h3 id="spAkademikTitle" class="sp-card__title">Identitas Akademik</h3>
                        <p class="sp-card__desc">Data resmi yang dikelola Admin — hubungi laboran untuk koreksi.</p>
                    </div>
                    <span class="sp-status sp-status--neutral">Baca-Saja</span>
                </div>

                <dl class="sp-facts-list">
                    <div>
                        <dt>Nama Lengkap</dt>
                        <dd>{{ $user->nama_lengkap }}</dd>
                    </div>
                    <div>
                        <dt>NIM</dt>
                        <dd class="sp-mono">{{ $user->nitk_nidn }}</dd>
                    </div>
                    <div>
                        <dt>Program Studi</dt>
                        <dd>{{ $prodiName ?: 'Belum diatur' }}</dd>
                    </div>
                    <div>
                        <dt>Kelas &amp; Angkatan</dt>
                        <dd>{{ $user->kelas ?: '—' }} @if($user->angkatan) &bull; Angkatan {{ $user->angkatan }} @endif</dd>
                    </div>
                    <div>
                        <dt>Semester &amp; Sesi Kuliah</dt>
                        <dd>
                            @if($user->semester) Semester {{ $user->semester }} ({{ ucfirst($user->semester_type ?? '-') }}) @else — @endif
                            &bull; Sesi {{ strtoupper($user->shift ?: 'pagi') }}
                        </dd>
                    </div>
                </dl>
            </section>

            <section class="sp-card" id="kontak-pemulihan" aria-labelledby="spKontakTitle">
                <div class="sp-card__head">
                    <div>
                        <h3 id="spKontakTitle" class="sp-card__title">Kontak &amp; Pemulihan</h3>
                        <p class="sp-card__desc">Kelola informasi kontak untuk keamanan dan pemulihan akun.</p>
                    </div>
                </div>

                <form method="POST" action="{{ route('mahasiswa.profile.contact') }}" class="sp-form">
                    @csrf

                    <div class="sp-field">
                        <div class="sp-field__head">
                            <label for="profileEmail" class="sp-label">Email Pribadi (Opsional)</label>
                            @if($isEmailVerified)
                                <span class="sp-status sp-status--ok">Terverifikasi (OTP)</span>
                            @elseif($hasEmail)
                                <span class="sp-status sp-status--warn">Menunggu OTP</span>
                            @else
                                <span class="sp-status sp-status--neutral">Belum Diisi</span>
                            @endif
                        </div>
                        <input id="profileEmail" type="email" name="email" class="sp-input" value="{{ old('email', $user->email) }}" placeholder="nama@mahasiswa.polibatam.ac.id" autocomplete="email">
                        <p class="sp-hint">Mengubah email akan meminta verifikasi kode OTP 6 digit ke alamat baru.</p>
                        @error('email')
                            <p class="sp-field-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="sp-field">
                        <div class="sp-field__head">
                            <label for="profilePhone" class="sp-label">Nomor WhatsApp / HP (Opsional)</label>
                            <span class="sp-status {{ $hasPhone ? 'sp-status--info' : 'sp-status--neutral' }}">
                                {{ $hasPhone ? 'Tersimpan' : 'Belum Diisi' }}
                            </span>
                        </div>
                        <input id="profilePhone" type="tel" name="no_hp" class="sp-input" value="{{ old('no_hp', $user->no_hp) }}" placeholder="081234567890" autocomplete="tel">
                        <p class="sp-hint">Untuk koordinasi praktikum — sistem tidak mengirim SMS OTP.</p>
                        @error('no_hp')
                            <p class="sp-field-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <button type="submit" class="sp-btn sp-btn--primary sp-btn--sm">Simpan Data Kontak</button>
                    </div>
                </form>

                @if(!empty($pendingEmailOtp))
                    <div class="sp-otp-card">
                        <div>
                            <span class="sp-status sp-status--warn">Verifikasi Email Tertunda</span>
                            <h4>Masukkan Kode OTP 6 Digit</h4>
                            <p>Kode verifikasi telah dikirimkan ke <strong>{{ $user->email }}</strong>. Masukkan kode untuk memverifikasi email Anda:</p>
                        </div>

                        <form method="POST" action="{{ route('mahasiswa.profile.email.verify') }}" class="sp-otp-row">
                            @csrf
                            <input type="text" name="otp_code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" placeholder="000000" required class="sp-input sp-mono sp-otp-input">
                            <button type="submit" class="sp-btn sp-btn--primary sp-btn--sm">Verifikasi OTP</button>
                        </form>
                        @error('otp_code')
                            <p class="sp-field-error">{{ $message }}</p>
                        @enderror

                        <form method="POST" action="{{ route('mahasiswa.profile.email.resend') }}">
                            @csrf
                            <button type="submit" class="sp-btn sp-btn--ghost sp-btn--xs" style="padding:0; height:auto; text-decoration:underline">Belum menerima email? Kirim ulang kode OTP</button>
                        </form>
                    </div>
                @endif
            </section>
        </div>

        {{-- Status biometrik --}}
        <section class="sp-card" aria-labelledby="spBioTitle">
            <div class="sp-card__head">
                <div>
                    <h3 id="spBioTitle" class="sp-card__title">Status Biometrik &amp; Mesin Absensi</h3>
                    <p class="sp-card__desc">Status pendaftaran sidik jari untuk keperluan absensi laboratorium.</p>
                </div>
            </div>
            @if($hasFingerId && $hasFingerTemplate)
                <div class="sp-attn__item">
                    <span class="sp-attn__icon sp-attn__icon--ok">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M12 11c1.5-1 3-3 3-5.5A3.5 3.5 0 0 0 11.5 2 3.5 3.5 0 0 0 8 5.5c0 .5.1 1 .2 1.5M5.5 9.5A7.5 7.5 0 0 0 4 15c0 2 .5 4 1.5 5.5M12 13.5V15a8 8 0 0 1-.8 3.6M15.5 10c.3 1 .5 2 .5 3a10 10 0 0 1-1.2 4.8"/></svg>
                    </span>
                    <div class="sp-attn__body">
                        <strong>Template sidik jari terekam di server</strong>
                        <span>ID mesin #{{ $user->finger_id }} · data biometrik tersimpan — siap disinkronkan ke mesin absensi.</span>
                    </div>
                </div>
            @elseif($hasFingerId)
                <div class="sp-attn__item">
                    <span class="sp-attn__icon sp-attn__icon--warn">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M12 9v4M12 17h.01"/><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/></svg>
                    </span>
                    <div class="sp-attn__body">
                        <strong>ID #{{ $user->finger_id }} dialokasikan, belum rekam sidik jari</strong>
                        <span>Lakukan perekaman di mesin absensi dengan pendampingan laboran.</span>
                    </div>
                </div>
            @else
                <div class="sp-attn__item">
                    <span class="sp-attn__icon sp-attn__icon--neutral">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M12 8v4M12 16h.01"/></svg>
                    </span>
                    <div class="sp-attn__body">
                        <strong>Belum memiliki ID mesin</strong>
                        <span>Alokasi ID sidik jari dilakukan Admin saat pendaftaran biometrik.</span>
                    </div>
                </div>
            @endif
            <p class="sp-hint" style="margin-top:12px">
                Jika data kelas/sesi tidak sesuai atau mesin fingerprint tidak membaca jari Anda, hubungi laboran untuk sinkronisasi ulang.
            </p>
        </section>
    </div>
@endsection
