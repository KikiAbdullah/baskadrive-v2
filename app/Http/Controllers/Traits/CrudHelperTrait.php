<?php

namespace App\Http\Controllers\Traits;

use Carbon\Carbon;
use File;
use Intervention\Image\Laravel\Facades\Image;

trait CrudHelperTrait
{
    // Data Things
    public function indexData($type)
    {
        // M-17: pakai primary key aktual model (tabel master tidak punya kolom `id`)
        $keyName = $this->model->getKeyName();

        if ($type) {
            return $this->model->withTrashed()->with($this->relation)->orderBy($keyName, 'DESC')->get();
        } else {
            return $this->model->with($this->relation)->orderBy($keyName, 'DESC')->get();
        }
    }

    // Generate things
    public function generateViewName($type)
    {
        if ($this->folder != '') {
            $view_name = $this->folder.'.'.$this->generateFolderName().'.'.$type;
        } else {
            $view_name = $this->generateFolderName().'.'.$type;
        }

        return $view_name;
    }

    public function generateFolderName()
    {
        $current_title = strtolower($this->title);

        return str_replace(' ', '_', $current_title);
    }

    public function generateUrl($type)
    {
        if ($this->folder != '') {
            $url = $this->folder.'.'.$this->makeDashCase(strtolower($this->title)).'.'.$type;
        } else {
            $url = $this->makeDashCase(strtolower($this->title)).'.'.$type;
        }

        return $url;
    }

    public function makeDashCase($title)
    {
        $dash_case = str_replace(' ', '-', $title);

        return $dash_case;
    }

    // role
    public function checkRoleExists()
    {
        $new_role = '';
        $auth_role = strtolower(str_replace(' ', '-', auth()->user()->roles->first()->name));

        if (in_array($auth_role, $this->role)) {
            $new_role = $auth_role;
        }

        return $new_role;
    }

    // Redirect things
    public function redirectSuccess($type, $isAjax)
    {
        $message = null;
        switch ($type) {
            case 'store':
                $message = 'Data Added Successfully';
                break;
            case 'update':
                $message = 'Data Saved Successfully';
                break;
            case 'destroy':
                $message = 'Data Deleted Successfully';
                break;
        }
        if ($isAjax) {
            return $message;
        } else {
            return redirect()->route($this->generateUrl('index'))
                ->withSuccess($message);
        }
    }

    public function redirectBackWithError($message)
    {
        return redirect()->back()->withInput()->withErrors($message);
    }

    /**
     * Terjemahkan error constraint database menjadi pesan manusiawi (audit M-04),
     * tanpa membocorkan SQL mentah / struktur skema ke pengguna.
     */
    protected function friendlyDbError(\Illuminate\Database\QueryException $e): string
    {
        $code = (int) ($e->errorInfo[1] ?? 0);

        return match (true) {
            $code === 1062 => 'Penyimpanan ditolak: terdapat nilai unik (nama/kode/username/email/NIK/SIM/plat/VIN) yang sudah dipakai data lain.',
            in_array($code, [1451, 1452], true) => 'Operasi ditolak: data ini masih terkait dengan transaksi lain sehingga tidak dapat diubah/dihapus.',
            in_array($code, [1048, 1138], true) => 'Ada kolom wajib yang kosong — periksa kembali isian formulir.',
            default => 'Penyimpanan data gagal karena kendala teknis basis data. Silakan hubungi administrator.',
        };
    }

    /**
     * Referensi objek untuk log aktivitas: nama kelas + primary key aktual (audit M-13).
     */
    protected function logReference($model): string
    {
        return class_basename($model).'#'.$model->getKey();
    }

    /**
     * Periksa apakah baris data masih dirujuk tabel lain lewat foreign key (audit M-07).
     * Membaca metadata information_schema sehingga bekerja generik untuk semua model
     * CrudTrait tanpa konfigurasi relasi manual. Null = aman dihapus.
     */
    protected function blockedByRelations($model): ?string
    {
        try {
            $fks = \DB::select(
                'SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE
                 WHERE REFERENCED_TABLE_SCHEMA = DATABASE()
                   AND REFERENCED_TABLE_NAME = ?
                   AND REFERENCED_COLUMN_NAME = ?',
                [$model->getTable(), $model->getKeyName()]
            );
        } catch (\Throwable $e) {
            return null; // driver tanpa information_schema (sqlite test) -> lewati guard
        }

        foreach ($fks as $fk) {
            try {
                $used = \DB::table($fk->TABLE_NAME)->where($fk->COLUMN_NAME, $model->getKey())->exists();
            } catch (\Throwable $e) {
                continue;
            }
            if ($used) {
                return 'Data tidak dapat dihapus karena masih digunakan oleh data pada tabel "'
                    .$fk->TABLE_NAME.'" (kolom '.$fk->COLUMN_NAME.').';
            }
        }

        return null;
    }

    public function redirectWithSessionFlash($message)
    {
        return redirect()->route($this->generateUrl('index'))
            ->with($message);
    }

    // others
    public function completeUrl()
    {
        return [
            'index' => $this->generateUrl('index'),
            'destroy' => $this->generateUrl('destroy'),
            'create' => $this->generateUrl('create'),
            'edit' => $this->generateUrl('edit'),
            'show' => $this->generateUrl('show'),
        ];
    }

    public function getRequest()
    {
        if (method_exists($this, 'customRequest')) {
            $request = $this->customRequest(app($this->model_request));
        } else {
            $model_request = app($this->model_request);
            $request = $model_request->all();
        }

        return $request;
    }

    /**
     * Ubah string kosong ('') menjadi null pada field numerik/tanggal/unique opsional,
     * agar lolos rule nullable numeric/date & tidak menabrak constraint unique NOT NULL (M-03/M-04).
     * Data hasil normalisasi di-merge kembali ke request sebelum validate().
     */
    protected function blanksToNull($request, array $keys): array
    {
        $data = $request->all();

        foreach ($keys as $key) {
            if (array_key_exists($key, $data) && $data[$key] === '') {
                $data[$key] = null;
            }
        }

        $request->merge($data);

        return $data;
    }

    public function validationRelation($model)
    {
        $response = [];

        foreach ($this->relation as $relasi) {
            $check = $this->checkArray($model->$relasi->toArray());

            if ($check) {
                $response = [
                    'icon' => 'error',
                    'message' => 'Data '.class_basename($model).' digunakan di '.$this->camelCaseToSpace($relasi),
                ];
            }
        }

        return $response;
    }

    public function camelCaseToSpace($relasi)
    {
        $kata = preg_replace('/(?<=\w)(?=[A-Z])/', ' $1', $relasi);

        $string = trim($kata);

        return ucwords($string);
    }

    public function checkArray($model)
    {
        $result = null;

        if (count($model) == count($model, COUNT_RECURSIVE)) {
            $result = false;
        } else {
            $result = true;
        }

        return $result;
    }

    public function saveFoto($file, $lokasi)
    {
        $filename = null;

        if (! empty($file)) {
            if (! File::isDirectory(storage_path().'/app/public/'.$lokasi)) {
                File::makeDirectory(storage_path().'/app/public/'.$lokasi, 0777, true);
            }

            if (substr($file->getMimeType(), 0, 5) == 'image') {
                if (! empty($file)) {
                    $extension = $file->getClientOriginalExtension();
                    $filename = md5($file->getFilename().Carbon::now()).'.'.$extension;

                    $location = storage_path().'/app/public/'.$lokasi.'/'.$filename;
                    Image::decode($file)->save($location);
                }
            } else {
                if (! empty($file)) {
                    $extension = $file->getClientOriginalExtension();
                    $filename = md5($file->getFilename().Carbon::now()).'.'.$extension;

                    $file->storeAs('public/'.$lokasi, $filename);
                }
            }
        }

        return $filename;
    }

    public function delImage($filename, $lokasi)
    {
        $path = storage_path().'/app/public/'.$lokasi.'/';

        return File::delete($path.$filename);
    }
}
