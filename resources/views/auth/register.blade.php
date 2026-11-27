@extends('layouts.app')

@section('title', 'Daftar Akun — '.config('app.name'))

@section('content')
<div class="card shadow-sm">
    <div class="card-body">
        <h4 class="mb-1">Buat akun</h4>
        <p class="text-muted mb-4">Daftar untuk mulai menggunakan {{ config('app.name') }}.</p>

        <form method="POST" action="{{ route('register') }}">
            @csrf

            <div class="mb-3">
                <label for="name" class="form-label">Nama</label>
                <input id="name" type="text" class="form-control @error('name') is-invalid @enderror"
                    name="name" value="{{ old('name') }}" required autocomplete="name" autofocus />
                @error('name')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <input id="email" type="email" class="form-control @error('email') is-invalid @enderror"
                    name="email" value="{{ old('email') }}" required autocomplete="email" />
                @error('email')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input id="password" type="password" class="form-control @error('password') is-invalid @enderror"
                    name="password" required autocomplete="new-password" />
                @error('password')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-4">
                <label for="password-confirm" class="form-label">Konfirmasi password</label>
                <input id="password-confirm" type="password" class="form-control"
                    name="password_confirmation" required autocomplete="new-password" />
            </div>

            <button type="submit" class="btn btn-primary d-grid w-100">Daftar</button>
        </form>

        <p class="text-center text-muted mt-4 mb-0">
            Sudah punya akun? <a href="{{ route('login') }}">Masuk</a>
        </p>
    </div>
</div>
@endsection
