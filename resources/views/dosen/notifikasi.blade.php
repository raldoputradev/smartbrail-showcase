@extends('layouts.dashboard')

@section('title', 'Balasan Saran')
@section('page-title', 'Balasan Saran')
@section('page-subtitle', 'Balasan admin pada saran Anda — info lab & mesin hanya lewat Pengumuman')

@section('sidebar-nav')
    @include('dosen._sidebar')
@endsection

@section('content')
    <div class="card animate-in">
        <div class="card-body" style="display:grid;gap:12px;">
            @forelse($opsItems as $item)
                <a href="{{ $item['url'] }}" style="display:block;padding:14px 16px;border-radius:10px;border:1px solid var(--border-subtle);text-decoration:none;color:inherit;background:rgba(99,102,241,0.04);">
                    <div style="font-weight:700;color:#1e293b;">{{ $item['title'] }}</div>
                    <div style="font-size:13px;color:var(--text-muted);margin-top:4px;">{{ $item['body'] }}</div>
                </a>
            @empty
                <p style="color:var(--text-muted);margin:0;">Tidak ada antrian operasional saat ini.</p>
            @endforelse
        </div>
    </div>
@endsection
