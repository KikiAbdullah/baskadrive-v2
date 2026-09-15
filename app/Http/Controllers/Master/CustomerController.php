<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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
            ->addColumn('full_name', function ($c) {
                $name = e($c->full_name);
                if ($c->is_blacklisted) {
                    $name .= ' <span class="badge bg-danger ms-1">Blacklist</span>';
                }
                return $name;
            })
            ->editColumn('customer_type', fn ($c) => $c->customer_type === 'individual' ? 'Individu' : 'Korporasi')
            ->editColumn('is_verified', fn ($c) => $c->is_verified
                ? '<span class="badge bg-success">Verified</span>'
                : '<span class="badge bg-warning">Pending</span>')
            ->rawColumns(['is_verified', 'full_name'])
            ->make(true);
    }

    public function customRequest($request)
    {
        // Normalisasi '' -> null untuk kolom unik/nullable (M-04: 'Duplicate entry'')
        $data = $this->blanksToNull($request, [
            'company_name', 'email', 'id_card_number', 'driver_license_number',
        ]);

        $rules = [
            'first_name' => 'required|string|max:50',
            'customer_type' => ['required', Rule::in(['individual', 'corporate'])],
            'phone' => ['required', 'string', 'max:20', 'regex:/^[0-9+\-\s()]{7,20}$/'],
            'email' => [
                'nullable', 'email:rfc', 'max:100',
                Rule::unique('m_customer', 'email')->ignore($request->route('id'), 'customer_id'),
            ],
            'id_card_number' => [
                'nullable', 'regex:/^\d{16}$/',
                Rule::unique('m_customer', 'id_card_number')->ignore($request->route('id'), 'customer_id'),
            ],
            'driver_license_number' => [
                'nullable', 'string', 'max:30',
                Rule::unique('m_customer', 'driver_license_number')->ignore($request->route('id'), 'customer_id'),
            ],
            // Audit M-09: alasan wajib saat blacklist dicentang
            'blacklist_reason' => [
                Rule::requiredIf(fn () => $request->boolean('is_blacklisted')),
                'nullable', 'string', 'max:500',
            ],
        ];

        $request->validate($rules, [
            'first_name.required' => 'Nama depan wajib diisi.',
            'phone.required' => 'Nomor telepon wajib diisi (dipakai untuk notifikasi WA).',
            'phone.regex' => 'Format nomor telepon tidak valid.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email ":input" sudah terdaftar sebagai pelanggan lain.',
            'id_card_number.regex' => 'NIK harus 16 digit angka.',
            'id_card_number.unique' => 'NIK tersebut sudah terdaftar.',
            'driver_license_number.unique' => 'Nomor SIM tersebut sudah terdaftar.',
            'blacklist_reason.required' => 'Alasan blacklist wajib diisi saat pelanggan ditandai sebagai bermasalah.',
        ]);

        // blacklist fields are boolean/text, no extra validation needed
        $data['is_blacklisted'] = $request->boolean('is_blacklisted');
        return $data;
    }

    /**
     * Audit trail blacklist/unblacklist (M-09): catat siapa & kapan.
     */
    public function customUpdate($data, $model, array $originalAttributes = [])
    {
        $was = (bool) ($originalAttributes['is_blacklisted'] ?? false);
        $now = (bool) ($data['is_blacklisted'] ?? false);

        if ($was !== $now) {
            \App\Models\UserLog::create([
                'user_id' => auth()->id(),
                'action' => $now ? 'add' : 'update',
                'menu' => 'master.customer',
                'message' => ($now ? 'Mem-blacklist' : 'Menghapus blacklist').' pelanggan Customer#'.$model->customer_id
                    .($now ? ' — alasan: '.($data['blacklist_reason'] ?? '-') : ''),
            ]);
        }
    }

    public function verify($id)
    {
        $this->model->findOrFail($id)->update(['is_verified' => true]);

        return redirect()->back()->withSuccess('Pelanggan berhasil diverifikasi.');
    }

    public function rentalNow($id)
    {
        return redirect()->route('rental.create', ['customer' => $id]);
    }
}