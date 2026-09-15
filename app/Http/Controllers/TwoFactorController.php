<?php

namespace App\Http\Controllers;

use Cookie;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TwoFactorController extends Controller
{
    public function __construct()
    {
        $this->middleware('two_factor_success');
    }

    public function verifyTwoFactor(Request $request)
    {
        $request->validate([
            'otp' => 'required',
        ]);

        $user = Auth::user();
        $stillValid = $user->token_2fa_expires_at && $user->token_2fa_expires_at->isFuture();

        // Perbandingan strict terhadap hash + cek masa berlaku 5 menit (audit 2.9)
        if ($stillValid && \Hash::check((string) $request->input('otp'), (string) $user->token_2fa)) {
            $user->forceFill(['token_2fa' => null, 'token_2fa_expires_at' => null])->save();

            $lifetime = time() + 60 * 60 * 24 * 365;
            Cookie::queue(config('2fa.cookie_name'), $user->id, $lifetime);

            return redirect($request->redirect ?? '/');
        } else {
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
