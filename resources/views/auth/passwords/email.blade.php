@extends('layouts.app')

@section('title', 'Lupa Password — '.config('app.name'))

@section('content')
<div class="card shadow-sm">
    <div class="card-body">
        <h4 class="mb-1">Lupa password?</h4>
        <p class="text-muted mb-4">Masukkan email akun Anda — kami akan mengirim tautan penggantian password.</p>

        @if (session('status'))
            <div class="alert alert-success" role="alert">{{ session('status') }}</div>
        @endif

        <form method="POST" action="{{ route('password.email') }}">
            @csrf
            <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <input id="email" type="email" class="form-control @error('email') is-invalid @enderror"
                    name="email" value="{{ old('email') }}" placeholder="nama@perusahaan.com"
                    required autocomplete="email" autofocus />
                @error('email')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
            </div>
            <button type="submit" class="btn btn-primary d-grid w-100">Kirim tautan reset</button>
        </form>

        <p class="text-center text-muted mt-4 mb-0">
            <a href="{{ route('login') }}">Kembali ke halaman masuk</a>
        </p>
    </div>
</div>
@endsection
