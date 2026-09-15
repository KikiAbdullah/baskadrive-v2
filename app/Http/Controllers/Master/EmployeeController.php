<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Yajra\DataTables\Facades\DataTables;

class EmployeeController extends Controller
{
    public function __construct(Employee $model)
    {
        $this->middleware('auth');

        $this->title = 'Employee';
        $this->subtitle = 'Data Karyawan Internal';
        $this->model_request = Request::class;
        $this->folder = 'master';
        $this->relation = [];
        $this->model = $model;
        $this->withTrashed = false;
    }

    public function customRequest($request)
    {
        $isUpdate = (bool) $request->route('id');

        $data = $this->blanksToNull($request, ['last_name', 'phone', 'hire_date', 'username', 'password_hash']);

        unset($data['_token'], $data['_method'], $data['create_account'], $data['user_role']);

        // M-04: cegah 'Duplicate entry' via unique rule; field NOT NULL diwajibkan di sini
        $request->validate([
            'first_name' => 'required|string|max:50',
            'last_name' => 'nullable|string|max:50',
            'email' => [
                'required', 'email:rfc', 'max:100',
                Rule::unique('m_employee', 'email')->ignore($request->route('id'), 'employee_id'),
            ],
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+\-\s()]{7,20}$/'],
            'position' => 'required|string|max:50',
            'username' => [
                'required', 'string', 'min:4', 'max:50', 'regex:/^[A-Za-z0-9._\-]+$/',
                Rule::unique('m_employee', 'username')->ignore($request->route('id'), 'employee_id'),
            ],
            'hire_date' => 'required|date',
            'role' => ['required', Rule::in(['admin', 'manager', 'cashier', 'mechanic', 'accountant', 'director'])],
            'password_hash' => $isUpdate ? 'nullable|min:8' : 'required|min:8',
            'is_active' => 'nullable|boolean',
        ], [
            'first_name.required' => 'Nama depan wajib diisi.',
            'email.required' => 'Email wajib diisi.',
            'email.unique' => 'Email ":input" sudah terdaftar.',
            'username.required' => 'Username wajib diisi (minimal 4 karakter, tanpa spasi).',
            'username.regex' => 'Username hanya boleh huruf, angka, titik, strip, dan underscore.',
            'username.unique' => 'Username ":input" sudah dipakai.',
            'hire_date.required' => 'Tanggal masuk kerja wajib diisi.',
            'position.required' => 'Jabatan wajib diisi.',
            'password_hash.required' => 'Password wajib diisi saat menambah karyawan baru.',
            'password_hash.min' => 'Password minimal 8 karakter.',
            'phone.regex' => 'Format nomor telepon tidak valid.',
        ]);

        if (empty($data['password_hash'])) {
            unset($data['password_hash']);
        } else {
            $data['password_hash'] = \Illuminate\Support\Facades\Hash::make($data['password_hash']);
        }

        return $data;
    }

    public function customStore($data, $model)
    {
        if (! request()->boolean('create_account')) {
            return;
        }

        if (empty($model->username) || empty($model->email)) {
            \Log::warning('Create akun login dilewati: username/email karyawan kosong', ['employee_id' => $model->employee_id]);

            return;
        }

        $exists = \App\Models\User::where('username', $model->username)->orWhere('email', $model->email)->exists();
        if ($exists) {
            // Audit M-11: jangan diam — laporkan jelas ke user & log
            \Log::warning('Create akun login gagal: username/email sudah dipakai', [
                'username' => $model->username, 'email' => $model->email, 'employee_id' => $model->employee_id,
            ]);
            session()->flash('warning', 'Karyawan tersimpan, TETAPI akun login tidak dibuat karena username/email sudah dipakai. Buat/tautkan akun manual di menu User.');

            return;
        }

        $user = \App\Models\User::create([
            'name' => $model->full_name,
            'username' => $model->username,
            'email' => $model->email,
            'password' => request('password_hash') ?: 'password',
            'nowa' => $model->phone,
            'employee_id' => $model->employee_id, // Audit M-12: kunci one-to-one karyawan <-> akun
        ]);

        $role = request('user_role') ?: 'staff';
        try {
            $user->assignRole($role);
        } catch (\Throwable $e) {
            \Log::error('Assign role gagal untuk user '.$user->id.': '.$e->getMessage());
            session()->flash('warning', 'Akun login dibuat, tetapi penugasan role "' . $role . '" gagal: ' . $e->getMessage());
        }
    }

    public function ajaxData()
    {
        return DataTables::of($this->model->query())
            ->addColumn('full_name', fn ($e) => $e->full_name)
            ->editColumn('is_active', fn ($e) => $e->is_active
                ? '<span class="badge bg-success">Aktif</span>'
                : '<span class="badge bg-secondary">Nonaktif</span>')
            ->rawColumns(['is_active'])
            ->make(true);
    }

    public function toggleActive($id)
    {
        $item = $this->model->findOrFail($id);

        $item->update(['is_active' => ! $item->is_active]);

        return redirect()->back()->withSuccess('Status karyawan diperbarui.');
    }
}