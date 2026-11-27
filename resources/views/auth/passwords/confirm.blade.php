@extends('layouts.app')

@section('title', 'Konfirmasi Password — '.config('app.name'))

@section('content')
<div class="card shadow-sm">
    <div class="card-body">
        <h4 class="mb-1">Konfirmasi password</h4>
        <p class="text-muted mb-4">Masukkan password Anda untuk melanjutkan.</p>

        <form method="POST" action="{{ route('password.confirm') }}">
            @csrf
            <div class="mb-4">
                <label for="password" class="form-label">Password</label>
                <input id="password" type="password" class="form-control @error('password') is-invalid @enderror"
                    name="password" required autocomplete="current-password" autofocus />
                @error('password')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
            </div>

            <button type="submit" class="btn btn-primary d-grid w-100">Konfirmasi</button>
        </form>

        @if (Route::has('password.request'))
            <p class="text-center text-muted mt-4 mb-0">
                <a href="{{ route('password.request') }}">Lupa password?</a>
            </p>
        @endif
    </div>
</div>
@endsection
