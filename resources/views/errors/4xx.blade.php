@extends('errors.layout')
@section('code', '4xx')
@section('accent', '#38bdf8')
@section('title', 'Permintaan Tidak Valid')
@section('message')Permintaan tidak dapat diproses. Muat ulang halaman dan coba lagi; bila berulang, hubungi administrator lab.@endsection
@section('actions')
    <a class="err-btn err-btn--ghost" href="javascript:history.back()">← Kembali</a>
    <a class="err-btn err-btn--primary" href="{{ url('/') }}">Ke Beranda</a>
@endsection
