<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\Coa;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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

    public function customRequest($request)
    {
        $data = $this->blanksToNull($request, ['parent_id']);

        $currentId = (int) $request->route('id') ?: null;

        $request->validate([
            'account_code' => [
                'required', 'string', 'max:20', 'regex:/^\d{1,2}-\d{3,4}$/',
                Rule::unique('m_coa', 'account_code')->ignore($currentId, 'account_id'),
            ],
            'account_name' => 'required|string|max:100',
            'account_type' => ['required', Rule::in(['asset', 'liability', 'equity', 'income', 'expense'])],
            'parent_id' => ['nullable', 'exists:m_coa,account_id'],
            'is_active' => 'nullable|boolean',
        ], [
            'account_code.required' => 'Kode akun wajib diisi.',
            'account_code.regex' => 'Format kode akun tidak valid. Gunakan pola nomor induk, misal "1-1100" atau "4-3000".',
            'account_code.unique' => 'Kode akun ":input" sudah terdaftar.',
            'account_name.required' => 'Nama akun wajib diisi.',
            'parent_id.exists' => 'Akun induk dipilih tidak ditemukan.',
        ]);

        // Kode akun yang sudah dipakai mutasi jurnal tidak boleh diganti (menjaga integritas resolveCoa otomatis)
        if ($currentId && isset($data['account_code'])) {
            $existing = Coa::find($currentId);
            $codeChanged = $existing && $existing->account_code !== $data['account_code'];
            if ($codeChanged && \App\Models\JournalDetail::where('account_id', $currentId)->exists()) {
                throw ValidationException::withMessages([
                    'account_code' => 'Kode akun tidak dapat diubah karena akun sudah memiliki mutasi jurnal.',
                ]);
            }
        }

        // Cegah siklus: parent tidak boleh diri sendiri atau turunan akun ini (M-03/M-15)
        $parentId = $data['parent_id'] ?? null;
        if ($parentId && $currentId) {
            if ((int) $parentId === $currentId) {
                throw ValidationException::withMessages([
                    'parent_id' => 'Akun tidak dapat dijadikan induk atas dirinya sendiri.',
                ]);
            }

            $frontier = [(int) $currentId];
            $seen = $frontier;
            while ($frontier && count($seen) < 500) {
                $frontier = Coa::whereIn('parent_id', $frontier)->pluck('account_id')->map(fn ($v) => (int) $v)->all();
                $frontier = array_values(array_diff($frontier, $seen));
                $seen = array_merge($seen, $frontier);
                if (in_array((int) $parentId, $frontier, true)) {
                    throw ValidationException::withMessages([
                        'parent_id' => 'Akun induk tidak boleh merupakan turunan dari akun ini (hierarki membentuk lingkaran).',
                    ]);
                }
            }
        }

        return $data;
    }

    public function formData()
    {
        $excludeIds = [];
        if ($currentId = request()?->route('id')) {
            $excludeIds = [(int) $currentId];
            $frontier = $excludeIds;
            while ($frontier && count($excludeIds) < 500) {
                $frontier = $this->model->whereIn('parent_id', $frontier)->pluck('account_id')->map(fn ($v) => (int) $v)->all();
                $frontier = array_values(array_diff($frontier, $excludeIds));
                $excludeIds = array_merge($excludeIds, $frontier);
            }
        }

        return [
            'list_parent' => $this->model
                ->whereNotIn('account_id', $excludeIds)
                ->selectRaw("account_id, CONCAT(account_code, ' - ', account_name) AS account_label")
                ->orderBy('account_code')
                ->pluck('account_label', 'account_id')
                ->toArray(),
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