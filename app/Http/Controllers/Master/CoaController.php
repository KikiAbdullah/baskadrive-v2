<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\Coa;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

class CoaController extends Controller
{
    public function __construct(Coa $model)
    {
        $this->middleware('auth');

        $this->title = 'Coa';
        $this->subtitle = 'Bagan Akun';
        $this->model_request = Request::class;
        $this->folder = 'master';
        $this->relation = ['parent'];
        $this->model = $model;
        $this->withTrashed = false;
    }

    public function formData()
    {
        return [
            'list_parent' => $this->model->selectRaw("account_id, CONCAT(account_code, ' - ', account_name) AS account_label")->orderBy('account_code')->pluck('account_label', 'account_id')->toArray(),
        ];
    }

    public function ajaxData()
    {
        return DataTables::of($this->model->with('parent')->select('m_coa.*'))
            ->addColumn('parent', fn ($c) => $c->parent ? $c->parent->account_code.' - '.$c->parent->account_name : '-')
            ->editColumn('account_type', fn ($c) => match ($c->account_type) {
                'asset' => '<span class="badge bg-primary">Aset</span>',
                'liability' => '<span class="badge bg-warning">Kewajiban</span>',
                'equity' => '<span class="badge bg-success">Ekuitas</span>',
                'income' => '<span class="badge bg-info">Pendapatan</span>',
                default => '<span class="badge bg-danger">Beban</span>',
            })
            ->rawColumns(['account_type'])
            ->make(true);
    }

    public function tree()
    {
        return response()->json($this->model->orderBy('account_code')->get());
    }
}