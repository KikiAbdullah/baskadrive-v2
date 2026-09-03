<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Providers\RouteServiceProvider;
use Cookie;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = RouteServiceProvider::HOME;

    /**
     * Create a new controller instance.
     */
    public function __construct()
    {
        $this->middleware('guest')->except('logout');
    }

    public function showLoginForm()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $this->validateLogin($request);

        Cookie::queue(Cookie::forget(config('2fa.cookie_name')));

        if ($this->attemptLogin($request)) {
            return $this->sendLoginResponse($request);
        }

        return $this->sendFailedLoginResponse($request);
    }

    protected function sendLoginResponse(Request $request)
    {
        $request->session()->regenerate();

        return redirect()->intended($this->redirectTo);
    }

    protected function sendFailedLoginResponse(Request $request)
    {
        throw ValidationException::withMessages([
            'msg_error' => [trans('Wrong username or password.')],
        ]);
    }

    protected function validateLogin(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);
    }

    protected function attemptLogin(Request $request)
    {
        $user = User::where('username', $request->username)->first();

        if (! $user) {
            return false;
        }

        // Verifikasi password menggunakan bcrypt (Hash::check)
        if (! Hash::check($request->password, $user->password)) {
            return false;
        }

        $this->guard()->login($user, $request->filled('remember'));

        return true;
    }

    public function logout(Request $request)
    {
        $this->guard()->logout();

        Cookie::queue(Cookie::forget(config('2fa.cookie_name')));

        $request->session()->flush();

        $request->session()->regenerate();

        return redirect('/');
    }

    protected function guard()
    {
        return Auth::guard();
    }
}