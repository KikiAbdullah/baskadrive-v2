<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AppController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $data['title'] = 'Dashboard';
        $data['subtitle'] = 'Hi, '.auth()->user()->name;

        return view('home')->with($data);
    }
}
