<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Yajra\DataTables\Facades\DataTables;

class BrandController extends Controller
{
    public function __construct(Brand $model)
    {
        $this->middleware('auth');

        $this->title = 'Brand';
        $this->subtitle = 'Merek Kendaraan';
        $this->model_request = Request::class;
        $this->folder = 'master';
        $this->relation = [];
        $this->model = $model;
        $this->withTrashed = false;
    }

    public function customRequest($request)
    {
        $data = $this->blanksToNull($request, ['logo_url']);

        $request->validate([
            'brand_name' => [
                'required', 'string', 'max:50',
                Rule::unique('m_brand', 'brand_name')->ignore($request->route('id'), 'brand_id'),
            ],
            'logo_url' => 'nullable|url|max:255',
        ], [
            'brand_name.required' => 'Nama merek wajib diisi.',
            'brand_name.unique' => 'Nama merek ":input" sudah terdaftar.',
            'logo_url.url' => 'URL logo harus format URL yang valid.',
        ]);

        return $data;
    }

    public function ajaxData()
    {
        return DataTables::of($this->model->query())
            ->make(true);
    }
}