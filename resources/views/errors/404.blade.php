@extends('errors.layout')
@section('code', '404')
@section('accent', '#38bdf8')
@section('title', 'Halaman Tidak Ditemukan')
@section('message')Alamat yang Anda buka tidak ada atau sudah dipindahkan. Periksa kembali tautannya, atau gunakan pencarian menu di portal.@endsection
@section('actions')
    <a class="err-btn err-btn--ghost" href="javascript:history.back()">← Kembali</a>
    <a class="err-btn err-btn--primary" href="{{ url('/') }}">Ke Beranda</a>
@endsection
