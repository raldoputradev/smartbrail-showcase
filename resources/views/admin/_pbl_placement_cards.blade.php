@php
    $showBelumCard = in_array($mhsFilter, ['semua', 'belum'], true)
        && ($mhsTim === '' || $mhsFilter === 'belum');
    $hasCards = $showBelumCard && ($mahasiswaBelum?->count() ?? 0) > 0;
    $hasCards = $hasCards || count($placementCards ?? []) > 0;
@endphp

@if(!$hasCards)
    <div class="card-body" style="padding:28px;text-align:center;color:var(--text-muted);">
        Tidak ada tim atau mahasiswa untuk filter ini.
    </div>
@else
    <div class="card-body">
        <div class="pbl-team-card-grid">
            @if($showBelumCard && ($mahasiswaBelum?->count() ?? 0) > 0)
                <button type="button" class="pbl-team-card pbl-team-card--pending js-pbl-team-open" data-detail-id="pbl-detail-unassigned" aria-label="Belum punya tim, {{ $mahasiswaBelum->count() }} mahasiswa">
                    <span class="pbl-team-card__badge pbl-team-card__badge--warn">Perlu penempatan</span>
                    <span class="pbl-team-card__kode">—</span>
                    <strong class="pbl-team-card__title">Belum punya tim</strong>
                    <p class="pbl-team-card__meta">{{ $mahasiswaBelum->count() }} mahasiswa menunggu tim</p>
                    <span class="pbl-team-card__cta">Lihat detail</span>
                    <span class="pbl-team-card__btn" aria-hidden="true">Buka detail tim</span>
                </button>
            @endif

            @foreach($placementCards as $card)
                @php
                    /** @var \App\Models\PblProject $project */
                    $project = $card['project'];
                    $ketua = $card['ketua'];
                @endphp
                <button type="button"
                    class="pbl-team-card js-pbl-team-open"
                    data-detail-id="pbl-detail-{{ $project->id }}"
                    aria-label="{{ $project->nama }}, {{ $card['count'] }} anggota">
                    <span class="pbl-team-card__badge">{{ $project->kode }}</span>
                    <strong class="pbl-team-card__title">{{ $project->nama }}</strong>
                    <p class="pbl-team-card__meta">
                        Sesi {{ ucfirst($project->shift_sesi) }}
                        · {{ $card['count'] }} anggota
                        @if(!$project->aktif) · <em>Nonaktif</em>@endif
                    </p>
                    <p class="pbl-team-card__staff">
                        Manpro: {{ $project->manproUser?->nama_lengkap ?? $project->manpro ?? '—' }}
                    </p>
                    @if($ketua?->user)
                        <p class="pbl-team-card__ketua">Ketua: {{ $ketua->user->nama_lengkap }}</p>
                    @endif
                    <span class="pbl-team-card__cta">Lihat detail</span>
                    <span class="pbl-team-card__btn" aria-hidden="true">Buka detail tim</span>
                </button>
            @endforeach
        </div>
    </div>

    @if($showBelumCard && ($mahasiswaBelum?->count() ?? 0) > 0)
        <div id="pbl-detail-unassigned" class="pbl-team-detail-source" hidden>
            <div class="pbl-team-detail__head">
                <span class="pbl-team-detail__badge pbl-team-detail__badge--warn">Belum tim</span>
                <h3>Mahasiswa tanpa penempatan</h3>
                <p>{{ $mahasiswaBelum->count() }} mahasiswa belum masuk tim PBL</p>
            </div>
            <section class="pbl-team-detail__section">
                <h4>Daftar mahasiswa</h4>
                <div class="pbl-team-detail__members-scroll">
                    <table class="data-table pbl-team-detail__table">
                        <thead>
                            <tr>
                                <th>NIM</th>
                                <th>Nama</th>
                                <th>Kelas</th>
                                <th>Semester</th>
                                <th>Penempatan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @include('admin._pbl_mahasiswa_rows', ['rows' => $mahasiswaBelum, 'projectOptions' => $projectOptions, 'compact' => true, 'showShift' => false])
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    @endif

    @foreach($placementCards as $card)
        @php
            $project = $card['project'];
            $members = $card['members'];
            $ketua = $card['ketua'];
            $wakil = $card['wakil'];
        @endphp
        <div id="pbl-detail-{{ $project->id }}" class="pbl-team-detail-source" hidden>
            <div class="pbl-team-detail__head">
                <span class="pbl-team-detail__badge">{{ $project->kode }}</span>
                <h3>{{ $project->nama }}</h3>
                <p>
                    Sesi {{ ucfirst($project->shift_sesi) }}
                    · {{ $members->count() }} anggota
                    @if($project->aktif)
                        · <span style="color:#0f766e;font-weight:700;">Aktif</span>
                    @else
                        · <span style="color:#b45309;font-weight:700;">Nonaktif</span>
                    @endif
                </p>
            </div>

            <section class="pbl-team-detail__section">
                <h4>Pengampu proyek</h4>
                <form method="POST" action="{{ route('admin.pbl.update-staff', $project) }}" class="pbl-staff-form">
                    @csrf
                    <div class="pbl-team-detail__roles">
                        <div class="pbl-staff-field">
                            <label for="staff_manpro_{{ $project->id }}">Manpro</label>
                            <select name="manpro_user_id" id="staff_manpro_{{ $project->id }}" required class="pbl-assign-select">
                                <option value="">— Pilih dosen —</option>
                                @foreach($dosenList as $d)
                                    <option value="{{ $d->id }}" @selected((int) $project->manpro_user_id === (int) $d->id)>{{ $d->nama_lengkap }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="pbl-staff-field">
                            <label for="staff_comanpro_{{ $project->id }}">Co-manpro</label>
                            <select name="co_manpro_user_id" id="staff_comanpro_{{ $project->id }}" class="pbl-assign-select">
                                <option value="">— Opsional —</option>
                                @foreach($dosenList as $d)
                                    <option value="{{ $d->id }}" @selected((int) $project->co_manpro_user_id === (int) $d->id)>{{ $d->nama_lengkap }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <button type="submit" class="pbl-assign-save" style="margin-top:10px;">Simpan pengampu</button>
                </form>
            </section>

            <section class="pbl-team-detail__section">
                <h4>Struktur tim</h4>
                <div class="pbl-team-detail__roles pbl-team-detail__roles--stack">
                    @include('partials._pbl_role_assign', [
                        'project' => $project,
                        'role' => 'ketua',
                        'label' => 'Ketua tim',
                        'current' => $ketua,
                        'members' => $members,
                        'storeRoute' => route('admin.pbl.ketua.store', $project),
                        'clearRoute' => route('admin.pbl.clear-role', $project),
                        'canEdit' => true,
                    ])
                    @include('partials._pbl_role_assign', [
                        'project' => $project,
                        'role' => 'wakil',
                        'label' => 'Wakil ketua',
                        'current' => $wakil,
                        'members' => $members,
                        'storeRoute' => route('admin.pbl.wakil.store', $project),
                        'clearRoute' => route('admin.pbl.clear-role', $project),
                        'canEdit' => true,
                    ])
                </div>
            </section>

            <section class="pbl-team-detail__section pbl-team-detail__section--members">
                <h4>Anggota tim ({{ $members->count() }})</h4>
                <p class="pbl-team-detail__note">Ketua tim dapat menambah anggota lewat aplikasi mobile jika tim masih kosong atau perlu penambahan.</p>
                <div class="pbl-team-detail__members-scroll">
                    @if($members->isEmpty())
                        <p class="pbl-team-detail__empty">Belum ada mahasiswa di tim ini untuk filter yang dipilih.</p>
                    @else
                        <table class="data-table pbl-team-detail__table">
                            <thead>
                                <tr>
                                    <th>NIM</th>
                                    <th>Nama</th>
                                    <th>Kelas</th>
                                    <th>Semester</th>
                                    <th>Shift</th>
                                    <th>Ubah penempatan</th>
                                </tr>
                            </thead>
                            <tbody>
                                @include('admin._pbl_mahasiswa_rows', ['rows' => $members, 'projectOptions' => $projectOptions, 'compact' => true])
                            </tbody>
                        </table>
                    @endif
                </div>
            </section>
        </div>
    @endforeach
@endif

<div class="pbl-modal-backdrop" id="pblTeamDetailModal" aria-hidden="true">
    <div class="pbl-modal pbl-team-detail-modal" role="dialog" aria-labelledby="pblTeamDetailTitle">
        <button type="button" class="pbl-modal-close" id="pblTeamDetailClose" aria-label="Tutup">
            @include('components.icon', ['name' => 'x'])
        </button>
        <div id="pblTeamDetailBody" class="pbl-team-detail-modal__body"></div>
    </div>
</div>
