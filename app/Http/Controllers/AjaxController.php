<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AjaxController extends Controller
{
    public function getButtonOption(Request $request)
    {
        $data = $request->all();

        $view = [
            'status' => true,
            'view' => view('layouts.button_option')->with(['id' => $data['id'], 'url' => $data['buttons']])->render(),
        ];

        return response()->json($view);
    }
}
