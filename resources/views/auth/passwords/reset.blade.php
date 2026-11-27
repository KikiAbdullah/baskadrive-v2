@extends('layouts.app')

@section('title', 'Atur Ulang Password — '.config('app.name'))

@section('content')
<div class="card shadow-sm">
    <div class="card-body">
        <h4 class="mb-1">Atur ulang password</h4>
        <p class="text-muted mb-4">Masukkan email dan password baru Anda.</p>

        <form method="POST" action="{{ route('password.update') }}">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}" />

            <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <input id="email" type="email" class="form-control @error('email') is-invalid @enderror"
                    name="email" value="{{ $email ?? old('email') }}" required autocomplete="email" autofocus />
                @error('email')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-3">
                <label for="password" class="form-label">Password baru</label>
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

            <button type="submit" class="btn btn-primary d-grid w-100">Simpan password baru</button>
        </form>
    </div>
</div>
@endsection
