@extends('errors.layout')
@section('code', '503')
@section('accent', '#a78bfa')
@section('title', 'Sedang Pemeliharaan')
@section('message')Sistem sedang dalam pemeliharaan terjadwal. Semua data aman — silakan kembali beberapa menit lagi.@endsection
@section('actions')
    <a class="err-btn err-btn--primary" href="javascript:location.reload()">Muat Ulang</a>
@endsection
