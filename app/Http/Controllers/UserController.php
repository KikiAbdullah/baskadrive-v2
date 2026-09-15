<?php

namespace App\Http\Controllers;

use App\Helpers\LogHelper;
use App\Http\Requests\UserRequest;
use App\Models\User;
use DB;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Yajra\DataTables\Facades\DataTables;

class UserController extends Controller
{
    public function __construct(User $model)
    {
        // S-02: nama permission harus sama dengan PermissionSeeder (users_*), bukan add_users
        $this->middleware('can:users_add', ['only' => ['store']]);
        $this->middleware('can:users_edit', ['only' => ['update']]);
        $this->middleware('can:users_delete', ['only' => ['destroy']]);

        $this->title = 'User';
        $this->subtitle = 'User Login';
        $this->model_request = UserRequest::class;
        $this->folder = 'user-setup';
        $this->relation = ['roles'];
        $this->model = $model;
        $this->withTrashed = true;
    }

    public function formData()
    {
        return ['list_role' => $this->listRolePluckId()];
    }

    public function ajaxData()
    {
        $query = $this->model->with(['roles']);

        if ($this->withTrashed) {
            $query->withTrashed();
        }

        return DataTables::of($query)
            ->addColumn('status', function ($data) {
                return st_aktif_badge($data->deleted_at);
            })
            ->addColumn('role', function ($data) {
                return $data->roles->first()->name ?? '-';
            })
            ->filterColumn('role', function ($query, $keyword) {
                $query->whereHas('roles', function ($q) use ($keyword) {
                    $q->where('name', 'LIKE', '%'.$keyword.'%');
                });
            })
            ->rawColumns(['status'])
            ->make(true);
    }

    public function customStore($data, $model)
    {
        // S-03: select form mengirim role ID (string) — resolve dulu ke model
        // karena Spatie hanya menerima int/UUID, string angka dilempar ke findByName
        $model->assignRole(Role::findOrFail((int) $data['role']));
    }

    public function update(Request $request, $id)
    {
        try {
            DB::beginTransaction();

            $data = $this->getRequest();

            if ($this->withTrashed) {
                $model = $this->model->withTrashed()->findOrFail($id);
            } else {
                $model = $this->model->findOrFail($id);
            }

            if (($data['password'] ?? '') == '') {
                $model->update([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'nowa' => $data['nowa'] ?? null,
                ]);
            } else {
                $model->update([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'nowa' => $data['nowa'] ?? null,
                    'password' => $data['password'],
                ]);
            }

            // S-03: bandingkan sebagai int (role dari form = ID string), lalu resolve ke model
            $roleId = (int) ($data['role'] ?? 0);
            if ($roleId !== 1) {
                if ($model->id == 1) {
                    $error = ValidationException::withMessages([
                        'SUPERADMIN' => "Can't disable or change roles to this user :)",
                    ]);
                    throw $error;
                }
            }
            $model->syncRoles([Role::findOrFail($roleId)]);

            if (($data['deleted_at_baru'] ?? ($model->trashed() ? '0' : '1')) == '1') {
                $model->restore();
            } else {
                //SUPERADMIN ID 1
                if ($model->id == 1) {
                    $error = ValidationException::withMessages([
                        'SUPERADMIN' => "Can't disable or change roles to this user :)",
                    ]);
                    throw $error;
                }
                $model->delete();
            }

            $model->save();

            $log_helper = new LogHelper;

            $log_helper->storeLog('edit', $model->id, $this->subtitle);

            DB::commit();

            if ($request->ajax()) {
                $response = [
                    'status' => true,
                    'msg' => $this->redirectSuccess(__FUNCTION__, true),
                ];

                return response()->json($response);
            } else {
                return $this->redirectSuccess(__FUNCTION__, false);
            }
        } catch (Exception $e) {
            DB::rollback();
            if ($request->ajax()) {
                $response = [
                    'status' => false,
                    'msg' => $e->getMessage(),
                ];

                return response()->json($response);
            } else {
                return $this->redirectBackWithError($e->getMessage());
            }
        }
    }

    public function destroy($id)
    {
        try {
            DB::beginTransaction();

            if ($id == 1) {
                $error = ValidationException::withMessages([
                    'SUPERADMIN' => "Can't disable or change roles to this user :)",
                ]);
                throw $error;
            }
            if ($this->withTrashed) {
                $model = $this->model->withTrashed()->with($this->relation)->findOrFail($id);
            } else {
                $model = $this->model->with($this->relation)->findOrFail($id);
            }

            if (method_exists($this, 'customDestroy')) {
                $this->customDestroy($model);
            }

            $log_helper = new LogHelper;

            $log_helper->storeLog('delete', $model->no ?? $model->id, $this->subtitle);

            $model->delete();

            DB::commit();

            return $this->redirectSuccess(__FUNCTION__, false);
        } catch (Exception $e) {
            DB::rollback();

            return $this->redirectBackWithError($e->getMessage());
        }
    }
}
