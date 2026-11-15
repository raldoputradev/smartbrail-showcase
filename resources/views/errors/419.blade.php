@extends('errors.layout')
@section('code', '419')
@section('accent', '#fbbf24')
@section('title', 'Sesi Kedaluwarsa')
@section('message')Halaman terbuka terlalu lama sehingga sesi amannya kedaluwarsa — tindakan Anda <b>belum</b> tersimpan. Muat ulang lalu ulangi sekali lagi.@endsection
@section('actions')
    <a class="err-btn err-btn--primary" href="javascript:location.reload()">Muat Ulang Halaman</a>
    <a class="err-btn err-btn--ghost" href="{{ url('/') }}">Ke Beranda</a>
@endsection
