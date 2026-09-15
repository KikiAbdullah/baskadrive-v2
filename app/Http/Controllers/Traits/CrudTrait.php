<?php

namespace App\Http\Controllers\Traits;

use App\Helpers\LogHelper;
use DB;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

trait CrudTrait
{
    use CrudHelperTrait;

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $view = [
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'folder' => $this->folder ?? '',
            'items' => method_exists($this, 'ajaxData') ? null : $this->indexData($this->withTrashed),
            'url' => array_merge(['store' => $this->generateUrl('store'), 'edit' => $this->generateUrl('edit'), 'destroy' => $this->generateUrl('destroy'), 'foto' => $this->generateUrl('foto')], $this->completeUrl()),
            'data' => method_exists($this, 'formData') ? $this->formData() : null,
            'form' => $this->generateViewName('form'),
        ];

        return view($this->generateViewName(__FUNCTION__))->with($view);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $view = [
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'folder' => $this->folder ?? '',
            'data' => method_exists($this, 'formData') ? $this->formData() : null,
            'form' => $this->generateViewName('form'),
            'url' => [
                'store' => $this->generateUrl('store'),
            ],
        ];

        return view($this->generateViewName(__FUNCTION__))->with($view);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        try {
            DB::beginTransaction();

            $data = $this->getRequest();

            $model = $this->model->fill($data);

            $model->save();

            if (method_exists($this, 'customStore')) {
                $this->customStore($data, $model);
            }

            $log_helper = new LogHelper;

            $log_helper->storeLog('add', $this->logReference($model), $this->subtitle);

            DB::commit();
            if ($request->ajax()) {
                $response = [
                    'status' => true,
                    'msg' => 'Data Saved.',
                ];

                return response()->json($response);
            } else {
                return $this->redirectSuccess(__FUNCTION__, false);
            }
        } catch (ValidationException $e) {
            DB::rollback();

            throw $e;
        } catch (QueryException $e) {
            DB::rollback();
            \Log::error('Crud query error: '.$e->getMessage());

            if ($request->ajax()) {
                return response()->json(['status' => false, 'msg' => $this->friendlyDbError($e)]);
            }

            return $this->redirectBackWithError($this->friendlyDbError($e));
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

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        if ($this->withTrashed) {
            $model = $this->model->withTrashed()->with($this->relation)->findOrFail($id);
        } else {
            $model = $this->model->with($this->relation)->findOrFail($id);
        }

        $view = [
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'folder' => $this->folder ?? '',
            'data' => method_exists($this, 'formData') ? $this->formData() : null,
            'id' => $id,
            'url' => array_merge(['store' => $this->generateUrl('store'), 'edit' => $this->generateUrl('edit'), 'destroy' => $this->generateUrl('destroy')], $this->completeUrl()),
            'item' => $model,
        ];

        return view($this->generateViewName(__FUNCTION__))->with($view);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit(Request $request, $id)
    {
        if ($this->withTrashed) {
            $model = $this->model->withTrashed()->with($this->relation)->findOrFail($id);
        } else {
            $model = $this->model->with($this->relation)->findOrFail($id);
        }

        $view = [
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'folder' => $this->folder ?? '',
            'id' => $id,
            'item' => method_exists($this, 'customItemEdit') ? $this->customItemEdit($model) : $model,
            'data' => method_exists($this, 'formData') ? $this->formData() : null,
            'form' => $this->generateViewName('form'),
            'url' => [
                'update' => $this->generateUrl('update'),
                'show' => $this->generateUrl('show'),
            ],
            'custom_data' => method_exists($this, 'customData') ? $this->customData($model) : null,
        ];
        if ($request->ajax()) {
            $response = [
                'status' => true,
                'view' => view($this->generateViewName(__FUNCTION__))->with($view)->render(),
            ];

            return response()->json($response);
        } else {
            return view($this->generateViewName(__FUNCTION__))->with($view);
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
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

            // Snapshot nilai asli SEBELUM fill/save — setelah save(), getOriginal()
            // sudah di-sync ke nilai baru sehingga audit-trail tidak akan pernah terdeteksi (M-01/M-02).
            $originalAttributes = $model->getOriginal();

            $model->fill($data);

            $model->save();

            if (method_exists($this, 'customUpdate')) {
                $this->customUpdate($data, $model, $originalAttributes);
            }

            $log_helper = new LogHelper;

            $log_helper->storeLog('edit', $this->logReference($model), $this->subtitle);

            DB::commit();
            if ($request->ajax()) {
                $response = [
                    'status' => true,
                    'msg' => 'Data Saved.',
                ];

                return response()->json($response);
            } else {
                return $this->redirectSuccess(__FUNCTION__, false);
            }
        } catch (ValidationException $e) {
            DB::rollback();

            throw $e;
        } catch (QueryException $e) {
            DB::rollback();
            \Log::error('Crud query error: '.$e->getMessage());

            if ($request->ajax()) {
                return response()->json(['status' => false, 'msg' => $this->friendlyDbError($e)]);
            }

            return $this->redirectBackWithError($this->friendlyDbError($e));
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

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        try {
            DB::beginTransaction();

            if ($this->withTrashed) {
                $model = $this->model->withTrashed()->with($this->relation)->findOrFail($id);
            } else {
                $model = $this->model->with($this->relation)->findOrFail($id);
            }

            // Audit M-07: tolak lebih awal dengan pesan ramah bila data masih dirujuk FK
            if ($blocked = $this->blockedByRelations($model)) {
                throw new Exception($blocked);
            }

            if (method_exists($this, 'customDestroy')) {
                $this->customDestroy($model);
            }

            $log_helper = new LogHelper;

            $log_helper->storeLog('delete', $this->logReference($model), $this->subtitle);

            $model->delete();

            DB::commit();

            return $this->redirectSuccess(__FUNCTION__, false);
        } catch (QueryException $e) {
            DB::rollback();
            \Log::error('Crud delete error: '.$e->getMessage());

            return $this->redirectBackWithError($this->friendlyDbError($e));
        } catch (Exception $e) {
            DB::rollback();

            return $this->redirectBackWithError($e->getMessage());
        }
    }
}
