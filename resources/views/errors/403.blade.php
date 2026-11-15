@extends('errors.layout')
@section('code', '403')
@section('accent', '#fb7185')
@section('title', 'Akses Ditolak')
@section('message')Anda tidak punya izin untuk membuka halaman ini. Bila merasa ini keliru, hubungi administrator lab — hak akses diatur per peran akun.@endsection
@section('actions')
    <a class="err-btn err-btn--ghost" href="javascript:history.back()">← Kembali</a>
    <a class="err-btn err-btn--primary" href="{{ url('/') }}">Ke Beranda</a>
@endsection
