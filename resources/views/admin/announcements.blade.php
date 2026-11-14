@extends('layouts.dashboard')

@section('title', 'Manajemen Pengumuman')
@section('page-title', 'Pengumuman')
@section('page-subtitle', 'Riwayat broadcast resmi · buat & edit lewat popup')

@section('sidebar-nav')
    @include('admin._sidebar')
@endsection

@section('styles')
    <link rel="stylesheet" href="{{ asset('css/comm-premium.css') }}?v=10">
@endsection

@section('content')
    @if(session('success'))
        <div class="panel-info-strip animate-in" style="margin-bottom:16px;border-color:rgba(15,118,110,0.3);">{{ session('success') }}</div>
    @endif

    @php
        $filterQuery = request()->only(['q', 'target', 'prioritas', 'page']);
        $isEdit = ($composerMode ?? 'create') === 'edit' && $selected;
        $openModal = request()->boolean('new') || $isEdit || $errors->any();
        $prio = old('prioritas', $selected->prioritas ?? 'normal');
        $tgt = old('target', $selected->target ?? 'semua');
        $expiresVal = old('expires_at', $selected?->expires_at?->format('Y-m-d\TH:i'));
        $authorName = $isEdit ? ($selected->author->nama_lengkap ?? 'Admin') : (auth()->user()->nama_lengkap ?? 'Admin');
        $createdLabel = $isEdit
            ? ($selected->created_at?->translatedFormat('d M Y, H:i') ?? '-')
            : now()->translatedFormat('d M Y, H:i');
        $formQuery = http_build_query(array_merge(
            $filterQuery,
            $selected ? ['id' => $selected->id] : ['new' => 1]
        ));
    @endphp

    <div class="ann-desk animate-in">
        <header class="ann-desk__hero">
            <div>
                <span class="ann-desk__eyebrow">Komunikasi resmi lab</span>
                <h2>Riwayat pengumuman</h2>
                <p>Tabel arsip ringkas. Buat atau edit lewat popup agar layar tetap fokus.</p>
            </div>
            <div class="ann-desk__kpis">
                <div class="ann-kpi"><span>Total</span><strong>{{ number_format($counts['total']) }}</strong></div>
                <div class="ann-kpi ann-kpi--ok"><span>Aktif</span><strong>{{ number_format($counts['aktif']) }}</strong></div>
                <div class="ann-kpi ann-kpi--warn"><span>Draft</span><strong>{{ number_format($counts['draft']) }}</strong></div>
                <div class="ann-kpi ann-kpi--muted"><span>Kadaluarsa</span><strong>{{ number_format($counts['kadaluarsa']) }}</strong></div>
            </div>
        </header>

        <div class="ann-desk__toolbar">
            <form method="GET" action="{{ route('admin.announcements.index') }}" class="ann-desk__filters" data-no-loader="1">
                <input type="search" name="q" class="ann-input" value="{{ request('q') }}" placeholder="Cari judul / isi…">
                <select name="target" class="ann-input">
                    <option value="">Semua target</option>
                    <option value="semua" @selected(request('target')==='semua')>Semua</option>
                    <option value="dosen" @selected(request('target')==='dosen')>Dosen</option>
                    <option value="mahasiswa" @selected(request('target')==='mahasiswa')>Mahasiswa</option>
                </select>
                <select name="prioritas" class="ann-input">
                    <option value="">Semua prioritas</option>
                    <option value="normal" @selected(request('prioritas')==='normal')>Normal</option>
                    <option value="penting" @selected(request('prioritas')==='penting')>Penting</option>
                    <option value="urgent" @selected(request('prioritas')==='urgent')>Urgent</option>
                </select>
                <button type="submit" class="ann-btn ann-btn--ghost">Filter</button>
                @if(request()->hasAny(['q','target','prioritas']))
                    <a href="{{ route('admin.announcements.index') }}" class="ann-btn ann-btn--ghost">Reset</a>
                @endif
            </form>
            <button type="button" class="ann-btn ann-btn--primary" id="annOpenCreate">
                @include('components.icon', ['name' => 'plus'])
                <span>Buat pengumuman</span>
            </button>
        </div>

        <div class="ann-desk__table-wrap card">
            <table class="data-table ann-desk__table">
                <thead>
                    <tr>
                        <th>Judul</th>
                        <th>Target</th>
                        <th>Prioritas</th>
                        <th>Status</th>
                        <th>Dibuat</th>
                        <th class="right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($items as $row)
                        @php
                            $expired = $row->expires_at && $row->expires_at->isPast();
                            $stateLabel = $expired ? 'Kadaluarsa' : ($row->is_active ? 'Aktif' : 'Draft');
                            $stateClass = $expired ? 'is-expired' : ($row->is_active ? 'is-live' : 'is-draft');
                            $prioClass = match ($row->prioritas) {
                                'urgent' => 'prio-urgent',
                                'penting' => 'prio-penting',
                                default => 'prio-normal',
                            };
                        @endphp
                        <tr>
                            <td>
                                <button type="button"
                                    class="ann-desk__title-btn js-ann-edit"
                                    data-id="{{ $row->id }}"
                                    title="Edit">
                                    {{ $row->judul }}
                                </button>
                                <div class="ann-desk__snippet">{{ \Illuminate\Support\Str::limit(strip_tags($row->isi), 80) }}</div>
                            </td>
                            <td>{{ ucfirst($row->target) }}</td>
                            <td><span class="ann-prio-chip {{ $prioClass }}">{{ ucfirst($row->prioritas) }}</span></td>
                            <td><span class="ann-state-pill {{ $stateClass }}">{{ $stateLabel }}</span></td>
                            <td class="ann-desk__mono">{{ $row->created_at?->translatedFormat('d M Y H:i') }}</td>
                            <td class="right">
                                <div class="ann-desk__row-actions">
                                    <button type="button" class="ann-btn ann-btn--ghost ann-btn--sm js-ann-edit" data-id="{{ $row->id }}">Edit</button>
                                    <form method="POST" action="{{ route('admin.announcements.toggle', $row) }}?{{ http_build_query(array_merge($filterQuery, ['id' => $row->id])) }}" class="inline-action-form">
                                        @csrf
                                        <button type="submit" class="ann-btn ann-btn--ghost ann-btn--sm">{{ $row->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.announcements.destroy', $row) }}?{{ http_build_query($filterQuery) }}" class="inline-action-form"
                                        data-swal-confirm data-swal-title="Hapus pengumuman?" data-swal-confirm-button="Ya, hapus" data-swal-confirm-color="#f43f5e">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="ann-btn ann-btn--danger ann-btn--sm">Hapus</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="ann-empty">Belum ada pengumuman. Klik <strong>Buat pengumuman</strong>.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            @if($items->hasPages())
                <div class="ann-desk__pager">{{ $items->withQueryString()->links() }}</div>
            @endif
        </div>
    </div>

    {{-- Modal compose --}}
    <div class="ann-modal-backdrop {{ $openModal ? 'is-open' : '' }}" id="annModal" role="dialog" aria-modal="true" aria-labelledby="annModalTitle" @unless($openModal) hidden @endunless>
        <div class="ann-modal">
            <header class="ann-modal__head">
                <div>
                    <h3 id="annModalTitle">{{ $isEdit ? 'Edit pengumuman' : 'Pengumuman baru' }}</h3>
                    <p>Pratinjau di kanan mengikuti ketikan — seperti tampilan penerima.</p>
                </div>
                <button type="button" class="ann-modal__close" id="annModalClose" aria-label="Tutup">×</button>
            </header>

            <div class="ann-modal__grid" id="annComposerRoot">
                <form method="POST"
                      id="annComposerForm"
                      action="{{ $isEdit ? route('admin.announcements.update', $selected) : route('admin.announcements.store') }}?{{ $formQuery }}"
                      class="ann-modal__form">
                    @csrf
                    <input type="hidden" name="_method" id="annComposerMethod" value="PUT" @disabled(!$isEdit)>

                    <label class="ann-field">
                        <span>Judul</span>
                        <input type="text" name="judul" id="ann_judul" class="ann-title-input" required maxlength="200"
                               placeholder="Judul singkat…" value="{{ old('judul', $selected->judul ?? '') }}">
                    </label>

                    <div class="ann-field">
                        <span>Prioritas</span>
                        <div class="ann-seg" role="radiogroup">
                            @foreach(['normal' => 'Normal', 'penting' => 'Penting', 'urgent' => 'Urgent'] as $val => $label)
                                <label class="ann-seg__opt ann-seg__opt--{{ $val }} {{ $prio === $val ? 'is-on' : '' }}">
                                    <input type="radio" name="prioritas" value="{{ $val }}" @checked($prio === $val) required>
                                    {{ $label }}
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div class="ann-field">
                        <span>Target audiens</span>
                        <div class="ann-seg" role="radiogroup">
                            @foreach(['semua' => 'Semua', 'dosen' => 'Dosen', 'mahasiswa' => 'Mahasiswa'] as $val => $label)
                                <label class="ann-seg__opt {{ $tgt === $val ? 'is-on' : '' }}">
                                    <input type="radio" name="target" value="{{ $val }}" @checked($tgt === $val) required>
                                    {{ $label }}
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <label class="ann-field ann-field--half">
                        <span>Berlaku sampai <em>(opsional)</em></span>
                        <input type="datetime-local" name="expires_at" id="ann_expires" class="ann-input" value="{{ $expiresVal }}">
                    </label>

                    <label class="ann-field">
                        <span class="ann-field__row">Isi <small id="annCharCount">0 karakter</small></span>
                        <textarea name="isi" id="ann_isi" class="ann-body-input" rows="8" required
                                  placeholder="Tulis pesan resmi…">{{ old('isi', $selected->isi ?? '') }}</textarea>
                    </label>

                    <div class="ann-compose__meta" id="annComposerMeta" @if(!$isEdit) hidden @endif>
                        @if($isEdit)
                            <span>Dibuat {{ $createdLabel }} · {{ $authorName }}</span>
                        @endif
                    </div>

                    <footer class="ann-modal__actions">
                        <button type="button" class="ann-btn ann-btn--ghost" id="annModalCancel">Batal</button>
                        <button type="submit" name="action" value="draft" class="ann-btn ann-btn--ghost">Simpan draft</button>
                        <button type="submit" name="action" value="publish" class="ann-btn ann-btn--primary">
                            @include('components.icon', ['name' => 'megaphone'])
                            <span id="annPublishLabel">{{ $isEdit ? 'Publikasikan ulang' : 'Publikasikan' }}</span>
                        </button>
                    </footer>
                </form>

                <aside class="ann-preview ann-preview--modal" aria-live="polite">
                    <div class="ann-preview__head">
                        <span>Pratinjau penerima</span>
                        <small>Seperti di layar dosen / mahasiswa</small>
                    </div>
                    <article class="ann-preview__card" id="annPreviewCard" data-prio="{{ $prio }}">
                        <div class="ann-preview__card-top">
                            <span class="ann-preview__prio" id="annPreviewPrio">{{ ucfirst($prio) }}</span>
                            <span class="ann-preview__target" id="annPreviewTarget">{{ ucfirst($tgt) }}</span>
                        </div>
                        <h4 id="annPreviewTitle">{{ old('judul', $selected->judul ?? 'Judul pengumuman') ?: 'Judul pengumuman' }}</h4>
                        <div class="ann-preview__meta">
                            <span id="annPreviewWhen">{{ $createdLabel }}</span>
                            · oleh <strong id="annPreviewAuthor">{{ $authorName }}</strong>
                        </div>
                        <div class="ann-preview__body" id="annPreviewBody">{{ old('isi', $selected->isi ?? 'Isi pengumuman…') ?: 'Isi pengumuman…' }}</div>
                        <div class="ann-preview__foot" id="annPreviewExpires" @if(!$expiresVal) hidden @endif>
                            Berlaku sampai <span id="annPreviewExpiresVal">{{ $selected?->expires_at?->translatedFormat('d M Y H:i') ?? '' }}</span>
                        </div>
                    </article>
                </aside>
            </div>

            <div class="ann-compose__danger" id="annComposerSecondary" @if(!$isEdit) hidden @endif>
                <form method="POST" id="annToggleForm" action="{{ $isEdit ? route('admin.announcements.toggle', $selected) . '?' . $formQuery : '#' }}" class="inline-action-form">
                    @csrf
                    <button type="submit" class="ann-btn ann-btn--ghost" id="annToggleBtn">
                        {{ $isEdit && !$selected->is_active ? 'Aktifkan' : 'Nonaktifkan' }}
                    </button>
                </form>
                <form method="POST" id="annDestroyForm" action="{{ $isEdit ? route('admin.announcements.destroy', $selected) . '?' . $formQuery : '#' }}" class="inline-action-form"
                    data-swal-confirm data-swal-title="Hapus pengumuman?" data-swal-confirm-button="Ya, hapus" data-swal-confirm-color="#f43f5e">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="ann-btn ann-btn--danger">
                        @include('components.icon', ['name' => 'trash-2'])
                        <span>Hapus</span>
                    </button>
                </form>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
@php
    $initialJson = null;
    if ($isEdit && $selected) {
        $initialJson = [
            'id' => $selected->id,
            'judul' => $selected->judul,
            'isi' => $selected->isi,
            'target' => $selected->target,
            'prioritas' => $selected->prioritas,
            'is_active' => (bool) $selected->is_active,
            'expires_at' => $selected->expires_at?->format('Y-m-d\TH:i'),
            'expires_label' => $selected->expires_at?->translatedFormat('d M Y H:i'),
            'created_label' => $selected->created_at?->translatedFormat('d M Y, H:i'),
            'author' => $selected->author->nama_lengkap ?? 'Admin',
            'update_url' => route('admin.announcements.update', $selected) . '?' . $formQuery,
            'toggle_url' => route('admin.announcements.toggle', $selected) . '?' . $formQuery,
            'destroy_url' => route('admin.announcements.destroy', $selected) . '?' . $formQuery,
        ];
    }
@endphp
<script>
window.annBroadcastConfig = {
    storeUrl: @json(route('admin.announcements.store') . '?' . http_build_query(array_merge($filterQuery, ['new' => 1]))),
    jsonBase: @json(url('/admin/announcements/__ID__/json') . (count($filterQuery) ? '?' . http_build_query($filterQuery) : '')),
    createMode: @json((bool) request('new') || (!$isEdit && $openModal)),
    openOnLoad: @json($openModal),
    authorFallback: @json(auth()->user()->nama_lengkap ?? 'Admin'),
    initial: @json($initialJson),
};
</script>
<script src="{{ asset('js/ann-broadcast.js') }}?v=3"></script>
@endsection
