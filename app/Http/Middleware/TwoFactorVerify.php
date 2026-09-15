<?php

namespace App\Http\Middleware;

use App\Helpers\KirimWAHelper;
use Carbon\Carbon;
use Closure;
use Cookie;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class TwoFactorVerify
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('2fa.enabled')) {
            return $next($request);
        }

        $now = Carbon::now();
        $user = Auth::user();
        $lastrequest = $user->token_last_request ?? '1970-01-01 00:00:00';
        $token2fa = Cookie::get(config('2fa.cookie_name'));

        if (! empty($token2fa) && $user->id == $token2fa) {
            return $next($request);
        }

        $redirectUrl = route('2fa.show', ['redirect' => $request->fullUrl()]);

        if (date('Y-m-d H:i:s', strtotime($lastrequest.'+2 minutes')) < $now) {
            // Token disimpan sebagai hash dengan kedaluwarsa ketat 5 menit (audit 2.9)
            $otpPlain = (string) random_int(10000, 99999);
            $user->token_2fa = \Hash::make($otpPlain);
            $user->token_2fa_expires_at = $now->copy()->addMinutes(5);
            $user->save();
            // send wa
            if ($user->nowa <> '') {
                $user->token_last_request = $now;
                $user->save();
                $wa = new KirimWAHelper;
                if ($wa->kirim($user->nowa, config('app.name').' OTP Code', 'Your login code to '.config('app.name').' is : '.$otpPlain, "Don't share this code to anyone.")) {
                    return redirect($redirectUrl);
                } else {
                    return redirect($redirectUrl)->withErrors('Something went wrong, try again later.');
                }
            } else {
                return redirect($redirectUrl);
            }
        } else {
            return redirect($redirectUrl)->withErrors('Enter OTP Code that we\'ve sent you at '.$lastrequest);
        }
    }
}
