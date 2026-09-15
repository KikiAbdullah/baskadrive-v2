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

        // S-07: cookie berisi token acak, verifikasi via hash + expiry 8 jam (bukan ID polos)
        if (! empty($token2fa)
            && ! empty($user->two_factor_cookie_hash)
            && $user->two_factor_cookie_expires_at
            && $user->two_factor_cookie_expires_at->isFuture()
            && \Hash::check((string) $token2fa, (string) $user->two_factor_cookie_hash)) {
            return $next($request);
        }

        $redirectUrl = route('2fa.show', ['redirect' => $request->fullUrl()]);

        if (date('Y-m-d H:i:s', strtotime($lastrequest.'+2 minutes')) < $now) {
            // Token disimpan sebagai hash dengan kedaluwarsa ketat 5 menit (audit 2.9)
            $otpPlain = (string) random_int(10000, 99999);
            $user->token_2fa = \Hash::make($otpPlain);
            $user->token_2fa_expires_at = $now->copy()->addMinutes(5);
            $user->save();
            // send wa (S-08: fallback email bila WA gagal / tanpa nowa)
            $sent = false;
            if ($user->nowa <> '') {
                $user->token_last_request = $now;
                $user->save();
                $wa = new KirimWAHelper;
                $sent = $wa->kirim($user->nowa, config('app.name').' OTP Code', 'Your login code to '.config('app.name').' is : '.$otpPlain, "Don't share this code to anyone.");
                if ($sent) {
                    return redirect($redirectUrl);
                }
            }

            if (config('2fa.fallback_via_email') && ! empty($user->email)) {
                try {
                    \Illuminate\Support\Facades\Mail::raw(
                        'Your login code to '.config('app.name').' is : '.$otpPlain."\nDon't share this code to anyone.",
                        function ($message) use ($user) {
                            $message->to($user->email)->subject(config('app.name').' OTP Code');
                        }
                    );
                    $user->token_last_request = $now;
                    $user->save();

                    return redirect($redirectUrl);
                } catch (\Throwable $e) {
                    \Log::warning('2FA fallback email failed: '.$e->getMessage());
                }
            }

            return $sent ? redirect($redirectUrl) : redirect($redirectUrl)->withErrors('Something went wrong, try again later.');
        } else {
            return redirect($redirectUrl)->withErrors('Enter OTP Code that we\'ve sent you at '.$lastrequest);
        }
    }
}
