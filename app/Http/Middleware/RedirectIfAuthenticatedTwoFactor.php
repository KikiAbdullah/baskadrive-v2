<?php

namespace App\Http\Middleware;

use Closure;
use Cookie;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfAuthenticatedTwoFactor
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$guards): Response
    {
        if (! config('2fa.enabled')) {
            return redirect('/');
        }

        $token2fa = Cookie::get(config('2fa.cookie_name'));
        $user = Auth::user();

        if (! empty($token2fa) && $user->id == $token2fa) {
            return redirect('/');
        }

        return $next($request);
    }
}
