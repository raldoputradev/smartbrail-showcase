{{-- Fragment: table + pager (AJAX / full page) --}}
@php
    use App\Support\PermissionRegistry as P;
@endphp

<div class="um-table-wrap">
    <table class="data-table um-table">
        <thead>
            <tr>
                @if(P::canCurrent('users', 'update'))
                    <th style="width:40px;">
                        <input type="checkbox" id="bulkSelectAll" class="um-check" title="Pilih semua yang siap disetujui di halaman ini" aria-label="Pilih semua">
                    </th>
                @endif
                <th>User</th>
                <th>Identitas</th>
                <th>Akademik</th>
                <th>Enroll</th>
                <th>Status</th>
                <th style="text-align:right;">Aksi</th>
            </tr>
        </thead>
        <tbody>
            @forelse($users as $user)
                @php
                    $approval = $user->approval_status ?? ($user->is_active ? 'approved' : 'pending');
                    $readyToApprove = in_array($user->role, ['mahasiswa', 'dosen'], true)
                        && $user->email_verified_at
                        && $approval !== 'approved'
                        && $approval !== 'rejected'
                        && $approval !== \App\Support\AccountActivation::STATUS;
                @endphp
                <tr data-ready="{{ $readyToApprove && auth()->id() !== $user->id ? '1' : '0' }}">
                    @if(P::canCurrent('users', 'update'))
                        <td>
                            @if($readyToApprove && auth()->id() !== $user->id)
                                <input type="checkbox" name="user_ids[]" value="{{ $user->id }}" form="bulkApproveForm" class="bulk-approve-check um-check">
                            @endif
                        </td>
                    @endif
                    <td>
                        <div class="users-name-cell">
                            @include('partials.user-avatar', [
                                'user' => $user,
                                'size' => 36,
                                'tone' => $user->role === 'mahasiswa' ? 'teal' : ($user->role === 'dosen' ? 'blue' : 'amber'),
                            ])
                            <div class="users-name-cell__text">
                                <a href="{{ route('profiles.show', $user) }}" class="users-name-cell__link">{{ $user->nama_lengkap }}</a>
                                <span class="users-name-cell__meta">{{ $user->role_display }}</span>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div class="um-id-cell">
                            <span>{{ $user->email ?: '—' }}</span>
                            <code>{{ $user->nitk_nidn }}</code>
                        </div>
                    </td>
                    <td>
                        @if($user->role === 'mahasiswa')
                            <div class="users-akademik-line">
                                <span class="users-akademik-kelas">{{ $user->kelas ?: '—' }}</span>
                                <span class="users-akademik-sep">·</span>
                                <span class="users-akademik-tim">{{ $user->tim ?: '—' }}</span>
                            </div>
                            <div class="users-name-cell__meta">Angkatan {{ $user->angkatan ?: '—' }} · Sem {{ $user->semester ?: '—' }}</div>
                        @else
                            <span class="users-name-cell__meta">Panel web</span>
                        @endif
                    </td>
                    <td>
                        @if($user->role === 'mahasiswa')
                            @if($user->hasFingerprintEnrolled())
                                <span class="status-badge success">Sudah Enroll</span>
                            @elseif($user->hasLegacyFingerprintPlaceholder())
                                <span class="status-badge danger" title="Perlu enroll ulang">Legacy</span>
                            @elseif($user->finger_id)
                                <span class="status-badge warning">Belum Enroll</span>
                            @else
                                <span class="status-badge danger">Perlu Finger ID</span>
                            @endif
                            @if($user->finger_id)
                                <div class="users-name-cell__meta" style="margin-top:4px;">ID {{ $user->finger_id }}</div>
                            @endif
                        @else
                            <span class="users-name-cell__meta">—</span>
                        @endif
                    </td>
                    <td>
                        @include('partials.user-approval-badge', ['user' => $user])
                        @include('partials.user-login-security-badge', ['user' => $user])
                        @if($user->approval_note)
                            <div class="users-name-cell__meta" style="margin-top:4px;max-width:160px;">{{ \Illuminate\Support\Str::limit($user->approval_note, 70) }}</div>
                        @endif
                    </td>
                    <td style="text-align:right;">
                        <div class="users-table-actions">
                            <a href="{{ route('profiles.show', $user) }}" class="action-btn-icon action-btn-icon--view" title="Lihat profil">
                                @include('components.icon', ['name' => 'eye'])
                            </a>
                            @if(P::canCurrent('users', 'update') && ($user->hasLoginSecurityIssue() || $user->login_suspicious_at))
                                <form method="POST" action="{{ route('admin.users.unlock-login', $user) }}" class="inline-action-form"
                                    data-swal-confirm
                                    data-swal-title="Buka kunci login?"
                                    data-swal-text="Pengguna {{ $user->nama_lengkap }} bisa login lagi setelah dibuka. Status persetujuan akun tidak berubah."
                                    data-swal-icon="question"
                                    data-swal-confirm-button="Ya, Buka Kunci"
                                    data-swal-confirm-color="#0d9488">
                                    @csrf
                                    <button type="submit" class="action-btn-icon action-btn-icon--approve" title="Buka kunci login">
                                        @include('components.icon', ['name' => 'unlock'])
                                    </button>
                                </form>
                            @endif
                            @if(P::canCurrent('users', 'update') && auth()->id() !== $user->id && $readyToApprove)
                                <form method="POST" action="{{ route('admin.users.approve', $user) }}" class="inline-action-form"
                                    data-swal-confirm
                                    data-swal-title="Setujui akun?"
                                    data-swal-text="Akun {{ $user->nama_lengkap }} akan bisa login ke {{ $user->role === 'mahasiswa' ? 'aplikasi mobile' : 'panel web' }}."
                                    data-swal-icon="question"
                                    data-swal-confirm-button="Ya, Setujui"
                                    data-swal-confirm-color="#22c55e">
                                    @csrf
                                    <button type="submit" class="action-btn-icon action-btn-icon--approve" title="Setujui akun">
                                        @include('components.icon', ['name' => 'check-circle'])
                                    </button>
                                </form>
                            @endif
                            @if(P::canCurrent('users', 'create') && $approval === \App\Support\AccountActivation::STATUS)
                                <form method="POST" action="{{ route('admin.users.activation-code', $user) }}" class="inline-action-form"
                                    data-swal-confirm
                                    data-swal-title="Buat kode aktivasi baru?"
                                    data-swal-text="Kode lama {{ $user->nama_lengkap }} langsung tidak berlaku. Kode baru hanya ditampilkan sekali."
                                    data-swal-icon="question"
                                    data-swal-confirm-button="Ya, Buat Kode"
                                    data-swal-confirm-color="#0284c7">
                                    @csrf
                                    <button type="submit" class="action-btn-icon action-btn-icon--approve" title="Kode aktivasi baru">
                                        @include('components.icon', ['name' => 'key-round'])
                                    </button>
                                </form>
                            @endif
                            @if(P::canCurrent('users', 'update') && auth()->id() !== $user->id && $approval !== 'approved' && $approval !== 'rejected')
                                <form method="POST" action="{{ route('admin.users.reject', $user) }}" class="inline-action-form reject-user-form">
                                    @csrf
                                    <input type="hidden" name="approval_note" value="">
                                    <button type="button" class="action-btn-icon action-btn-icon--reject reject-user-btn" title="Tolak pendaftaran" data-user-name="{{ $user->nama_lengkap }}">
                                        @include('components.icon', ['name' => 'x-circle'])
                                    </button>
                                </form>
                            @endif
                            @if(P::canCurrent('users', 'update') && auth()->id() !== $user->id && $approval === 'approved')
                                <form method="POST" action="{{ route('admin.users.toggle-active', $user) }}" class="inline-action-form"
                                    data-swal-confirm
                                    data-swal-title="{{ $user->is_active ? 'Nonaktifkan akun?' : 'Aktifkan kembali?' }}"
                                    data-swal-text="{{ $user->is_active
                                        ? ($user->nama_lengkap . ' tidak bisa login sampai diaktifkan lagi. Status persetujuan tetap Disetujui.')
                                        : ($user->nama_lengkap . ' bisa login lagi.') }}"
                                    data-swal-icon="question"
                                    data-swal-confirm-button="{{ $user->is_active ? 'Ya, Nonaktifkan' : 'Ya, Aktifkan' }}"
                                    data-swal-confirm-color="{{ $user->is_active ? '#ea580c' : '#0d9488' }}">
                                    @csrf
                                    <button type="submit"
                                        class="action-btn-icon {{ $user->is_active ? 'action-btn-icon--reject' : 'action-btn-icon--approve' }}"
                                        title="{{ $user->is_active ? 'Nonaktifkan akun' : 'Aktifkan kembali' }}">
                                        @include('components.icon', ['name' => $user->is_active ? 'ban' : 'user-check'])
                                    </button>
                                </form>
                            @endif
                            @if(P::canCurrent('users', 'update'))
                                <button type="button" class="action-btn-icon action-btn-icon--edit" title="Edit user"
                                    data-update-url="{{ route('admin.users.update', $user) }}"
                                    data-name="{{ $user->nama_lengkap }}"
                                    data-nitk="{{ $user->nitk_nidn }}"
                                    data-email="{{ $user->email }}"
                                    data-role="{{ $user->role }}"
                                    data-kelas="{{ $user->kelas }}"
                                    data-tim="{{ $user->tim }}"
                                    data-program-studi-id="{{ $user->program_studi_id }}"
                                    data-shift="{{ $user->shift }}"
                                    data-angkatan="{{ $user->angkatan }}"
                                    data-semester="{{ $user->semester }}"
                                    data-semester-type="{{ $user->semester_type }}"
                                    data-finger-id="{{ $user->finger_id }}"
                                    data-active="{{ $user->is_active ? '1' : '0' }}"
                                    onclick="openUserModal('edit', this)">
                                    @include('components.icon', ['name' => 'pencil'])
                                </button>
                            @endif
                            @if(P::canCurrent('users', 'delete') && auth()->id() !== $user->id)
                                <form method="POST" action="{{ route('admin.users.destroy', $user) }}" class="inline-action-form"
                                    data-swal-confirm
                                    data-swal-title="Hapus user?"
                                    data-swal-text="User {{ $user->nama_lengkap }} akan dihapus permanen. Riwayat penghapusan tetap tersimpan untuk admin."
                                    data-swal-icon="warning"
                                    data-swal-confirm-button="Ya, Hapus"
                                    data-swal-confirm-color="#f43f5e">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="action-btn-icon action-btn-icon--delete" title="Hapus">
                                        @include('components.icon', ['name' => 'trash-2'])
                                    </button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ P::canCurrent('users', 'update') ? 7 : 6 }}" class="um-empty">
                        Belum ada data pengguna untuk filter ini.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="um-pager">
    @include('components.table-pagination', ['paginator' => $users])
</div>
<p class="um-list-meta" data-total="{{ $users->total() }}" data-page="{{ $users->currentPage() }}" hidden>
    {{ $users->total() }} akun · halaman {{ $users->currentPage() }}
</p>
