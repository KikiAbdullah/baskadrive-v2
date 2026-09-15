<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Providers\RouteServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class RegisterController extends Controller
{
    /**
     * Where to redirect users after registration.
     *
     * @var string
     */
    protected $redirectTo = RouteServiceProvider::HOME;

    /**
     * Create a new controller instance.
     */
    public function __construct()
    {
        $this->middleware('guest');
    }

    public function showRegistrationForm()
    {
        return view('auth.register');
    }

    public function register(Request $request)
    {
        // S-01: defense-in-depth — rute saja tidak cukup; bila route cache basi
        // atau flag dimatikan saat runtime, tetap tolak.
        if (! config('app.registration_enabled')) {
            abort(404);
        }

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = $this->registerUser($request->all());

        // S-01: pendaftar publik (bila fitur dinyalakan) HANYA boleh dapat role
        // terkecil VIEWER — tidak ada akses modul operasional.
        $user->assignRole(config('app.registration_default_role', 'VIEWER'));

        $this->guard()->login($user);

        $request->session()->regenerate();

        return redirect($this->redirectTo);
    }

    protected function registerUser(array $data)
    {
        return User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'username' => strtolower(str_replace(' ', '_', $data['name'])).rand(100, 999),
        ]);
    }

    protected function guard()
    {
        return Auth::guard();
    }
}