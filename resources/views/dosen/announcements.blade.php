@extends('layouts.dashboard')

@section('title', 'Pengumuman')
@section('page-title', 'Pengumuman')
@section('page-subtitle', 'Informasi terbaru dari laboran/admin')

@section('sidebar-nav')
    @include('dosen._sidebar')
@endsection

@section('content')
    <div class="card animate-in" style="margin-bottom:20px;">
        <div class="card-body">
            <form method="GET" action="{{ route('dosen.announcements') }}" class="filter-toolbar filter-toolbar-grid">
                <input type="text" name="q" value="{{ request('q') }}" placeholder="Cari pengumuman...">
                <button type="submit" class="topbar-btn"><span>🔎</span> Cari</button>
                <a href="{{ route('dosen.announcements') }}" class="topbar-btn" style="text-decoration:none;">@include('components.icon', ['name' => 'rotate-ccw'])<span>Reset</span></a>
            </form>
        </div>
    </div>

    <div style="display:grid;gap:14px;">
        @forelse($items as $a)
            @php $color = ['normal'=>'#4a90d9','penting'=>'#e8a93d','urgent'=>'#dc4a4a'][$a->prioritas] ?? '#4a90d9'; @endphp
            <div class="card animate-in" style="border-left:4px solid {{ $color }};">
                <div class="card-body">
                    <div style="display:flex;justify-content:space-between;align-items:start;gap:12px;flex-wrap:wrap;">
                        <div style="flex:1;min-width:240px;">
                            <h3 style="font-size:16px;font-weight:700;margin-bottom:6px;">{{ $a->judul }}</h3>
                            <div style="font-size:11px;color:var(--text-muted);margin-bottom:10px;">
                                {{ $a->created_at?->translatedFormat('d M Y H:i') }}
                                · oleh <strong>{{ $a->author->nama_lengkap ?? '-' }}</strong>
                                · target: <span style="text-transform:capitalize;">{{ $a->target }}</span>
                            </div>
                            <div style="font-size:13.5px;line-height:1.65;color:var(--text-dark);white-space:pre-wrap;">{{ $a->isi }}</div>
                        </div>
                        <div>
                            <span class="status-badge" style="background:{{ $color }}1a;color:{{ $color }};text-transform:capitalize;">
                                {{ $a->prioritas }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="card animate-in">
                <div class="card-body" style="text-align:center;padding:36px;color:var(--text-muted);">
                    📭 Belum ada pengumuman.
                </div>
            </div>
        @endforelse
    </div>

    <div style="margin-top:16px;">
        {{ $items->links() }}
    </div>
@endsection
