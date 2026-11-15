@extends('errors.layout')
@section('code', '500')
@section('accent', '#fb7185')
@section('title', 'Terjadi Kesalahan Sistem')
@section('message')Server gagal memproses permintaan — ini masalah di sisi kami, bukan di perangkat Anda. Kesalahan tercatat otomatis; bila berulang, hubungi administrator lab dengan menyebut kode ini.@endsection
@section('actions')
    <a class="err-btn err-btn--primary" href="javascript:location.reload()">Muat Ulang</a>
    <a class="err-btn err-btn--ghost" href="{{ url('/') }}">Ke Beranda</a>
@endsection
