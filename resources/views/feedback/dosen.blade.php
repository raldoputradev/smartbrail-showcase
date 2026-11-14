@extends('layouts.dashboard')

@section('title', 'Saran & Masukan')
@section('page-title', 'Saran & Masukan')
@section('page-subtitle', 'Kirim masukan ke admin laboran — balasan muncul di sini')

@section('sidebar-nav')
    @include('dosen._sidebar')
@endsection

@section('styles')
    <link rel="stylesheet" href="{{ asset('css/comm-premium.css') }}?v=4">
@endsection

@section('content')
    @include('feedback._body', [
        'isMhs' => false,
        'storeRoute' => 'dosen.feedback.store',
        'readRoute' => 'dosen.feedback.read',
    ])
@endsection

@section('scripts')
    <script src="{{ asset('js/emoji-toolbar.js') }}?v=4"></script>
@endsection
