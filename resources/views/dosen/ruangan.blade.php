@extends('layouts.dashboard')

@section('title', 'Pengajuan Ruangan Tim')
@section('page-title', 'Pengajuan Ruangan')
@section('page-subtitle', 'Pengajuan ruangan dari tim bimbingan Anda (Smart BRAIL)')

@section('sidebar-nav')
    @include('dosen._sidebar')
@endsection

@section('content')
@php $badge=['diajukan'=>'badge-info','menunggu_penerimaan'=>'badge-warn','disetujui'=>'badge-ok','ditolak'=>'badge-danger','dibatalkan'=>'badge-muted','selesai'=>'badge-muted','kedaluwarsa'=>'badge-muted'];
     $label=['diajukan'=>'Diajukan','menunggu_penerimaan'=>'Menunggu penerimaan waktu pengganti','disetujui'=>'Disetujui','ditolak'=>'Ditolak','dibatalkan'=>'Dibatalkan','selesai'=>'Selesai','kedaluwarsa'=>'Kedaluwarsa']; @endphp
<div class="card">
    @forelse($pengajuan as $p)
        @php $st=$p->statusEfektif(); @endphp
        <div style="padding:12px 0;border-bottom:1px solid var(--border,#e2e8f0)">
            <div style="display:flex;justify-content:space-between;gap:8px;flex-wrap:wrap">
                <strong>{{ $p->ruangan->nama }}</strong>
                <span class="badge {{ $badge[$st] ?? 'badge-info' }}">{{ $label[$st] ?? $st }}</span>
            </div>
            <div class="muted" style="font-size:.88rem;margin-top:4px">
                {{ $p->snapshot['tim']['nama'] ?? 'Tim' }} · {{ \Illuminate\Support\Carbon::parse($p->tanggal)->isoFormat('ddd, D MMM Y') }} {{ substr($p->jam_mulai,0,5) }}–{{ substr($p->jam_selesai,0,5) }} · {{ $p->tujuan }}
            </div>
            <div class="muted" style="font-size:.82rem">Pengaju: {{ $p->snapshot['pengaju']['name'] ?? '-' }} · Peserta: {{ collect($p->snapshot['peserta'] ?? [])->pluck('name')->implode(', ') }}@if($p->alasan_keputusan) · Keputusan: {{ $p->alasan_keputusan }}@endif</div>
        </div>
    @empty
        <p class="muted">Belum ada pengajuan dari tim bimbingan Anda.</p>
    @endforelse
</div>
@endsection
