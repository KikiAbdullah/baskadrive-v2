<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Yajra\DataTables\Facades\DataTables;

class DriverController extends Controller
{
    public function __construct(Driver $model)
    {
        $this->middleware('auth');

        $this->title = 'Driver';
        $this->subtitle = 'Data Sopir Mitra';
        $this->model_request = Request::class;
        $this->folder = 'master';
        $this->relation = [];
        $this->model = $model;
        $this->withTrashed = false;
    }

    public function customRequest($request)
    {
        $data = $this->blanksToNull($request, ['last_name', 'license_expiry', 'phone', 'notes']);

        if (isset($data['license_number'])) {
            $data['license_number'] = strtoupper(trim((string) $data['license_number']));
            $request->merge(['license_number' => $data['license_number']]);
        }

        // Komisi default 0 bila field kosong/tidak dikirim (checkbox-less input).
        $data['commission_percent'] = isset($data['commission_percent']) && $data['commission_percent'] !== null
            ? $data['commission_percent']
            : 0;

        $request->validate([
            'first_name' => 'required|string|max:50',
            'last_name' => 'nullable|string|max:50',
            'license_number' => [
                'required', 'string', 'min:6', 'max:30', 'regex:/^[0-9A-Z\-]+$/',
                Rule::unique('m_driver', 'license_number')->ignore($request->route('id'), 'driver_id'),
            ],
            'license_expiry' => 'nullable|date',
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+\-\s()]{7,20}$/'],
            'notes' => 'nullable|string|max:500',
            'is_active' => 'nullable|boolean',
            // Butir 2.2.5 audit_12092026: persentase komisi sopir 0-100%.
            'commission_percent' => 'nullable|numeric|min:0|max:100',
        ], [
            'first_name.required' => 'Nama depan sopir wajib diisi.',
            'license_number.required' => 'Nomor SIM wajib diisi.',
            'license_number.regex' => 'Nomor SIM hanya boleh berisi angka, huruf, dan tanda hubung.',
            'license_number.unique' => 'Nomor SIM ":input" sudah terdaftar.',
            'phone.regex' => 'Format nomor telepon tidak valid.',
            'commission_percent.min' => 'Komisi sopir tidak boleh negatif.',
            'commission_percent.max' => 'Komisi sopir maksimal 100%.',
        ]);

        return $data;
    }

    public function ajaxData()
    {
        return DataTables::of($this->model->query())
            ->addColumn('full_name', fn ($d) => $d->full_name)
            ->addColumn('sim_status', function ($d) {
                if (empty($d->license_expiry)) {
                    return '<span class="text-muted">-</span>';
                }
                $days = now()->diffInDays(Carbon::parse($d->license_expiry), false);
                if ($days < 0) {
                    return '<span class="badge bg-danger">Expired '.abs((int) $days).' hari</span>';
                }
                if ($days <= 30) {
                    return '<span class="badge bg-warning">Exp '.$d->license_expiry->format('d/m/Y').' ('.$days.' hari)</span>';
                }

                return '<span class="badge bg-success">Valid</span>';
            })
            ->editColumn('is_active', fn ($d) => $d->is_active
                ? '<span class="badge bg-success">Aktif</span>'
                : '<span class="badge bg-secondary">Nonaktif</span>')
            ->rawColumns(['is_active', 'sim_status'])
            ->make(true);
    }

    public function history($id)
    {
        $item = $this->model->with('rentals')->findOrFail($id);

        return view('master.driver.history')->with(['item' => $item]);
    }
}
