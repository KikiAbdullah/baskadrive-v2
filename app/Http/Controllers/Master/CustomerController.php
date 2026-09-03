<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

class CustomerController extends Controller
{
    public function __construct(Customer $model)
    {
        $this->middleware('auth');

        $this->title = 'Customer';
        $this->subtitle = 'Data Pelanggan';
        $this->model_request = Request::class;
        $this->folder = 'master';
        $this->relation = [];
        $this->model = $model;
        $this->withTrashed = false;
    }

    public function ajaxData()
    {
        return DataTables::of($this->model->query())
            ->addColumn('full_name', fn ($c) => $c->full_name)
            ->editColumn('customer_type', fn ($c) => $c->customer_type === 'individual' ? 'Individu' : 'Korporasi')
            ->editColumn('is_verified', fn ($c) => $c->is_verified
                ? '<span class="badge bg-success">Verified</span>'
                : '<span class="badge bg-warning">Pending</span>')
            ->rawColumns(['is_verified'])
            ->make(true);
    }

    public function verify($id)
    {
        $this->model->findOrFail($id)->update(['is_verified' => true]);

        return redirect()->back()->withSuccess('Pelanggan berhasil diverifikasi.');
    }

    public function rentalNow($id)
    {
        return redirect()->route('rental.create.wizard', ['customer' => $id]);
    }
}