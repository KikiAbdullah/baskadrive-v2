<?php

namespace App\Http\Controllers;

use Cookie;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class TwoFactorController extends Controller
{
    /**
     * Audit keamanan (OTP brute force): OTP 5 digit (~90 ribu kombinasi) tidak boleh
     * bisa dicoba tanpa batas. Selain throttle IP 5/menit di rute, OTP aktif DIBATALKAN
     * otomatis setelah 5 percobaan gagal per user (window 10 menit) — penyerang harus
     * meminta OTP baru, dan tiap permintaan OTP dibatasi jendela 2 menit oleh middleware.
     */
    private const MAX_OTP_ATTEMPTS = 5;

    public function __construct()
    {
        $this->middleware('two_factor_success');
    }

    protected function attemptsKey(int $userId): string
    {
        return 'otp_attempts:'.$userId;
    }

    public function verifyTwoFactor(Request $request)
    {
        $request->validate([
            'otp' => 'required',
        ]);

        $user = Auth::user();
        $attemptsKey = $this->attemptsKey($user->id);

        if (Cache::get($attemptsKey, 0) >= self::MAX_OTP_ATTEMPTS) {
            // OTP aktif dibuang — wajib minta kode baru (jendela 2 menit middleware).
            $user->forceFill(['token_2fa' => null, 'token_2fa_expires_at' => null, 'token_last_request' => null])->save();

            return redirect()->back()->withErrors('Terlalu banyak percobaan. Minta kode OTP baru.');
        }

        $stillValid = $user->token_2fa_expires_at && $user->token_2fa_expires_at->isFuture();

        // Perbandingan strict terhadap hash + cek masa berlaku 5 menit (audit 2.9)
        if ($stillValid && \Hash::check((string) $request->input('otp'), (string) $user->token_2fa)) {
            Cache::forget($attemptsKey);
            $user->forceFill(['token_2fa' => null, 'token_2fa_expires_at' => null])->save();

            // S-07: simpan token acak hash di DB, cookie hanya berisi token polos (bukan ID)
            $plainToken = Str::random(64);
            $user->forceFill([
                'two_factor_cookie_hash' => \Hash::make($plainToken),
                'two_factor_cookie_expires_at' => now()->addHours(8),
            ])->save();

            $lifetime = 8 * 60; // 8 jam dalam menit, selaras dengan expiry DB
            Cookie::queue(config('2fa.cookie_name'), $plainToken, $lifetime);

            return redirect($request->redirect ?? '/');
        } else {
            Cache::put($attemptsKey, Cache::get($attemptsKey, 0) + 1, now()->addMinutes(10));
            $user->forceFill(['token_2fa' => null, 'token_2fa_expires_at' => null, 'token_last_request' => null])->save();

            return redirect()->back()->withErrors('Your OTP Code is invalid or expired.');
        }
    }

    public function showTwoFactorForm()
    {
        $user = Auth::user();
        $nowa = '';
        $ujung = '';
        if (! empty($user->nowa)) {
            $target = $user->nowa;
            $ujung = substr($target, -2);
            $count = strlen($target) - 4;
            $output = substr_replace($target, str_repeat('*', $count), 4, $count);
            $output = substr($output, 0, -2);
            $nowa = $output;
        }
        $data['nowa'] = $nowa.$ujung;

        return view('auth.two_factor')->with($data);
    }
}
