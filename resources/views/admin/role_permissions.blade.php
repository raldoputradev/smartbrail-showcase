@extends('layouts.dashboard')

@section('title', 'Kelola Akses Role')
@section('page-title', 'Kelola Akses Role')
@section('page-subtitle', 'Opsional — hanya menu web dosen & admin laboran')

@section('sidebar-nav')
    @include('admin._sidebar')
@endsection

@section('content')
    <div class="card animate-in" style="margin-bottom:18px;border-color:rgba(74,144,217,0.35);">
        <div class="card-body" style="font-size:13px;line-height:1.6;color:var(--text-muted);">
            <strong style="color:var(--text-dark);">Halaman ini tidak ada di sidebar</strong> — buka lewat ikon perisai di bar atas (panel “Akses Anda”).
            Centang di bawah hanya memengaruhi <strong>dashboard web dosen</strong> dan menu admin laboran.
            <strong>Mahasiswa</strong> memakai aplikasi mobile; toggle di sini tidak mengubah menu Flutter.
        </div>
    </div>

    <style>
        .role-access-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 18px;
        }

        .role-access-card {
            border: 1px solid var(--border-subtle);
            border-radius: 18px;
            background: linear-gradient(180deg, rgba(255,255,255,0.98), rgba(248,251,255,0.95));
            box-shadow: 0 14px 36px rgba(15, 23, 42, 0.08);
            overflow: hidden;
        }

        .role-access-card__head {
            padding: 18px;
            border-bottom: 1px solid var(--border-subtle);
            background: rgba(74,144,217,0.08);
        }

        .role-access-card__head h3 {
            margin: 0 0 6px;
            font-size: 17px;
            font-weight: 800;
            color: var(--text-main);
        }

        .role-access-card__head p {
            margin: 0;
            font-size: 12.5px;
            color: var(--text-muted);
            line-height: 1.55;
        }

        .role-access-card__body {
            padding: 16px;
        }

        .role-access-section + .role-access-section {
            margin-top: 16px;
        }

        .role-access-section__title {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 8px;
            color: var(--text-muted);
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .role-access-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 12px 0;
            border-top: 1px solid rgba(148, 163, 184, 0.18);
        }

        .role-access-item:first-of-type {
            border-top: 0;
        }

        .role-access-item__label {
            min-width: 0;
        }

        .role-access-item__label strong {
            display: block;
            color: var(--text-main);
            font-size: 13.5px;
            line-height: 1.25;
        }

        .role-access-item__label span {
            display: block;
            margin-top: 3px;
            color: var(--text-muted);
            font-family: ui-monospace, SFMono-Regular, Consolas, monospace;
            font-size: 10.5px;
        }

        .access-switch {
            position: relative;
            display: inline-flex;
            align-items: center;
            width: 48px;
            height: 26px;
            flex: 0 0 auto;
        }

        .access-switch input {
            position: absolute;
            inset: 0;
            opacity: 0;
            cursor: pointer;
        }

        .access-switch__track {
            width: 100%;
            height: 100%;
            border-radius: 999px;
            background: #d8e0ea;
            box-shadow: inset 0 0 0 1px rgba(15, 23, 42, 0.08);
            transition: all .2s ease;
        }

        .access-switch__track::after {
            content: "";
            position: absolute;
            width: 20px;
            height: 20px;
            left: 3px;
            top: 3px;
            border-radius: 50%;
            background: #fff;
            box-shadow: 0 4px 10px rgba(15, 23, 42, 0.2);
            transition: transform .2s ease;
        }

        .access-switch input:checked + .access-switch__track {
            background: linear-gradient(135deg, #2d5a8e, #4a90d9);
        }

        .access-switch input:checked + .access-switch__track::after {
            transform: translateX(22px);
        }

        .access-note {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 10px;
            border-radius: 999px;
            background: rgba(16, 185, 129, 0.1);
            color: #047857;
            font-size: 11.5px;
            font-weight: 700;
        }

        @media (max-width: 1180px) {
            .role-access-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <div class="card animate-in" style="margin-bottom:20px;">
        <div class="card-body" style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
            <div>
                <h3 style="font-size:15px;font-weight:700;margin-bottom:4px;">Konfigurasi Hak Akses</h3>
                <p style="font-size:13px;color:var(--text-muted);">
                    Halaman ini sekarang hanya mengatur <strong>akses tampilan/menu</strong>. Jika dimatikan,
                    menu hilang dari sidebar dan akses URL langsung ikut diblokir oleh middleware.
                </p>
                <p style="font-size:12px;color:var(--text-muted);margin-top:6px;">
                    Checkbox CRUD dihapus dari tampilan agar tidak menyesatkan. Aksi buat/ubah/hapus tetap mengikuti route internal yang memang tersedia untuk role tersebut.
                </p>
            </div>
            <form method="POST" action="{{ route('admin.role.reset') }}"
                data-swal-confirm
                data-swal-title="Reset akses role?"
                data-swal-text="Semua akses akan dikembalikan ke konfigurasi default sistem."
                data-swal-icon="warning"
                data-swal-confirm-button="Ya, Reset"
                data-swal-confirm-color="#f43f5e">
                @csrf
                <button type="submit" class="topbar-btn" style="padding:10px 16px;border:1px solid var(--border-subtle);border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;">↺ Reset ke Default</button>
            </form>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.role.update') }}">
        @csrf
        @php
            $roleLabels = \App\Support\PermissionRegistry::roleLabels();
            $roleDescriptions = [
                'laboran' => 'Akses dashboard web admin, operasional IoT, laporan, pengumuman, user, dan pengaturan sistem.',
                'dosen' => 'Akses dashboard web dosen untuk melihat data mahasiswa, absensi, loker, pengumuman, dan profil sendiri.',
                'mahasiswa' => 'Mahasiswa tidak memakai dashboard web. Akses ini untuk fitur mobile seperti pengumuman dan data pribadi.',
            ];
            $roleMenuKeys = [
                'laboran' => [
                    'dashboard', 'users', 'mesin', 'log_absensi', 'log_loker', 'announcements',
                    'role_access', 'suggestions', 'notifications', 'monitoring',
                    'attendance', 'lockers', 'fingerprint', 'reports', 'settings', 'ai_assistant',
                ],
                'dosen' => ['dashboard', 'announcements', 'dosen_absensi', 'dosen_loker', 'dosen_mahasiswa', 'dosen_profile', 'ai_assistant'],
                'mahasiswa' => ['announcements', 'reports'],
            ];
            $groupedByRole = [];
            foreach ($roles as $role) {
                foreach ($roleMenuKeys[$role] ?? [] as $menuKey) {
                    if (!isset($menus[$menuKey])) continue;
                    [$label, $group] = $menus[$menuKey];
                    $groupedByRole[$role][$group][$menuKey] = $label;
                }
            }
        @endphp

        @foreach($roles as $role)
            @foreach($menus as $menuKey => $_menu)
                @unless(in_array($menuKey, $roleMenuKeys[$role] ?? [], true))
                    <input type="hidden" name="access[{{ $role }}][{{ $menuKey }}]" value="0">
                @endunless
            @endforeach
        @endforeach

        <div class="role-access-grid">
            @foreach($roles as $role)
                <div class="role-access-card animate-in">
                    <div class="role-access-card__head">
                        <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;">
                            <h3>{{ $roleLabels[$role] ?? ucfirst($role) }}</h3>
                            <span class="access-note">{{ count($roleMenuKeys[$role] ?? []) }} akses</span>
                        </div>
                        <p>{{ $roleDescriptions[$role] ?? 'Atur akses tampilan untuk role ini.' }}</p>
                    </div>
                    <div class="role-access-card__body">
                        @foreach(($groupedByRole[$role] ?? []) as $groupName => $menusInGroup)
                            <div class="role-access-section">
                                <div class="role-access-section__title">
                                    <span>{{ $groupName }}</span>
                                    <span>{{ count($menusInGroup) }} menu</span>
                                </div>

                                @foreach($menusInGroup as $menuKey => $label)
                                    @php
                                        $p = $matrix[$role][$menuKey] ?? null;
                                        $isActive = $p?->can_view ?? false;
                                    @endphp

                                    <div class="role-access-item">
                                        <div class="role-access-item__label">
                                            <strong>{{ $label }}</strong>
                                            <span>{{ $menuKey }}</span>
                                        </div>
                                        <label class="access-switch" title="Aktifkan/nonaktifkan akses tampilan">
                                            <input type="hidden" name="access[{{ $role }}][{{ $menuKey }}]" value="0">
                                            <input type="checkbox" name="access[{{ $role }}][{{ $menuKey }}]" value="1" @checked($isActive)>
                                            <span class="access-switch__track"></span>
                                        </label>
                                    </div>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>

        <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:20px;">
            <button type="submit" style="color:#fff;background:linear-gradient(135deg,#2d5a8e,#4a90d9);border:none;padding:12px 32px;border-radius:8px;font-weight:600;cursor:pointer;font-size:14px;">
                💾 Simpan Perubahan
            </button>
        </div>
    </form>
@endsection
