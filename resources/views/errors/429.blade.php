@extends('errors.layout')
@section('code', '429')
@section('accent', '#fbbf24')
@section('title', 'Terlalu Banyak Permintaan')
@section('message')Permintaan dikirim terlalu cepat dan ditahan untuk menjaga layanan tetap stabil. Tunggu sebentar lalu coba lagi.@endsection
@section('actions')
    <a class="err-btn err-btn--ghost" href="javascript:history.back()">← Kembali</a>
    <a class="err-btn err-btn--primary" href="javascript:setTimeout(() => location.reload(), 1500)">Coba Lagi</a>
@endsection
