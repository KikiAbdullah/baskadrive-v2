<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AjaxController extends Controller
{
    public function getButtonOption(Request $request)
    {
        $data = $request->all();
        $menuView = $data['buttons']['menu'] ?? 'layouts.button_option';

        $view = [
            'status' => true,
            'view' => view($menuView)->with([
                'id' => $data['id'],
                'url' => $data['buttons'],
                'toggle_label' => $data['toggle_label'] ?? null,
            ])->render(),
        ];

        return response()->json($view);
    }
}
