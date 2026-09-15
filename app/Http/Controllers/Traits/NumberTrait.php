<?php

namespace App\Http\Controllers\Traits;

use DB;
use Illuminate\Database\Eloquent\SoftDeletes;

trait NumberTrait
{
    /**
     * Query dasar penomoran: denganTrashed HANYA bila model memakai SoftDeletes,
     * agar gen_number tidak fatal pada model non-soft-delete (Rental/Invoice/Claim).
     * Menerima instance model maupun fully-qualified class name.
     */
    protected function genBaseQuery($model)
    {
        $class = is_object($model) ? get_class($model) : $model;

        return in_array(SoftDeletes::class, class_uses_recursive($class), true)
            ? $class::withTrashed()
            : $class::query();
    }

    public function gen_number_with_where_in($model, $column, $prefix, $date, $field_date, $every_month = false, $whereCol, $whereVal)
    {
        $year = '';
        $month = '';
        if (! empty($date)) {
            $year = date('Y', strtotime($date));
            $month = date('m', strtotime($date));
        }

        $jml = substr_count($prefix, '#');
        $increment = str_repeat('#', $jml);
        $pos = strpos($prefix, '#');
        $adaY = strpos($prefix, '$');
        $adaM = strpos($prefix, '@');
        //check model
        if (! empty($date)) {
            $lastno = $this->genBaseQuery($model)->select(DB::raw('IFNULL(MAX(CAST(SUBSTRING('.$column.', '.($pos + 1).','.$jml.') as UNSIGNED)),0) * 1 AS idmax'))->whereIn($whereCol, $whereVal);
            $lastno = $this->queryParams($lastno, ['adaY' => $adaY, 'adaM' => $adaM, 'every_month' => $every_month, 'field_date' => $field_date, 'year' => $year, 'month' => $month]);
            $lastno = $lastno->first()->idmax;
        } else {
            $lastno = $this->genBaseQuery($model)->select(DB::raw('IFNULL(MAX(CAST(SUBSTRING('.$column.', '.($pos + 1).','.$jml.') as UNSIGNED)),0) * 1 AS idmax'))->whereIn($whereCol, $whereVal)
                ->first()->idmax;
        }

        $count_dollar = substr_count($prefix, '$');

        $nextno = str_pad($lastno + 1, $jml, '0', STR_PAD_LEFT);
        $result = str_replace($increment, $nextno, $prefix);
        $result = str_replace(str_repeat('$', $count_dollar), substr($year, $count_dollar == 2 ? 2 : 0, $count_dollar), $result);
        $result = str_replace('@@', substr($month, 0, 2), $result);
        $result = str_replace('%%', $this->numberToRoman(date('m', strtotime($date))), $result);

        return $result;
    }

    public function gen_number_with_where($model, $column, $prefix, $date, $field_date, $every_month = false, $whereCol, $whereVal)
    {
        $year = '';
        $month = '';
        if (! empty($date)) {
            $year = date('Y', strtotime($date));
            $month = date('m', strtotime($date));
        }

        $jml = substr_count($prefix, '#');
        $increment = str_repeat('#', $jml);
        $pos = strpos($prefix, '#');
        $adaY = strpos($prefix, '$');
        $adaM = strpos($prefix, '@');
        //check model
        if (! empty($date)) {
            $lastno = $this->genBaseQuery($model)->select(DB::raw('IFNULL(MAX(CAST(SUBSTRING('.$column.', '.($pos + 1).','.$jml.') as UNSIGNED)),0) * 1 AS idmax'))->where($whereCol, $whereVal);
            $lastno = $this->queryParams($lastno, ['adaY' => $adaY, 'adaM' => $adaM, 'every_month' => $every_month, 'field_date' => $field_date, 'year' => $year, 'month' => $month]);
            $lastno = $lastno->first()->idmax;
        } else {
            $lastno = $this->genBaseQuery($model)->select(DB::raw('IFNULL(MAX(CAST(SUBSTRING('.$column.', '.($pos + 1).','.$jml.') as UNSIGNED)),0) * 1 AS idmax'))->where($whereCol, $whereVal)
                ->first()->idmax;
        }

        $count_dollar = substr_count($prefix, '$');

        $nextno = str_pad($lastno + 1, $jml, '0', STR_PAD_LEFT);
        $result = str_replace($increment, $nextno, $prefix);
        $result = str_replace(str_repeat('$', $count_dollar), substr($year, $count_dollar == 2 ? 2 : 0, $count_dollar), $result);
        $result = str_replace('@@', substr($month, 0, 2), $result);
        $result = str_replace('%%', $this->numberToRoman(date('m', strtotime($date))), $result);

        return $result;
    }

    public function gen_number($model, $column, $prefix, $date, $field_date, $every_month = false)
    {
        $year = '';
        $month = '';
        if (! empty($date)) {
            $year = date('Y', strtotime($date));
            $month = date('m', strtotime($date));
        }

        $jml = substr_count($prefix, '#');
        $increment = str_repeat('#', $jml);
        $pos = strpos($prefix, '#');
        $adaY = strpos($prefix, '$');
        $adaM = strpos($prefix, '@');
        //check model
        if (! empty($date)) {
            $lastno = $this->genBaseQuery($model)->select(DB::raw('IFNULL(MAX(CAST(SUBSTRING('.$column.', '.($pos + 1).','.$jml.') as UNSIGNED)),0) * 1 AS idmax'));
            $lastno = $this->queryParams($lastno, ['adaY' => $adaY, 'adaM' => $adaM, 'every_month' => $every_month, 'field_date' => $field_date, 'year' => $year, 'month' => $month]);
            $lastno = $lastno->first()->idmax;
        } else {
            $lastno = $this->genBaseQuery($model)->select(DB::raw('IFNULL(MAX(CAST(SUBSTRING('.$column.', '.($pos + 1).','.$jml.') as UNSIGNED)),0) * 1 AS idmax'))
                ->first()->idmax;
        }

        $count_dollar = substr_count($prefix, '$');

        $nextno = str_pad($lastno + 1, $jml, '0', STR_PAD_LEFT);
        $result = str_replace($increment, $nextno, $prefix);
        $result = str_replace(str_repeat('$', $count_dollar), substr($year, $count_dollar == 2 ? 2 : 0, $count_dollar), $result);
        $result = str_replace('@@', substr($month, 0, 2), $result);
        $result = str_replace('%%', $this->numberToRoman(date('m', strtotime($date))), $result);

        return $result;
    }

    public function queryParams($query, $arrDate)
    {
        if ($arrDate['adaY'] && $arrDate['adaM']) {
            $query->whereYear($arrDate['field_date'], $arrDate['year']);
            if ($arrDate['every_month']) {
                $query->whereMonth($arrDate['field_date'], $arrDate['month']);
            }
        } elseif ($arrDate['adaY']) {
            $query->whereYear($arrDate['field_date'], $arrDate['year']);
        }

        return $query;
    }

    public function numberToRoman($number)
    {
        $map = ['M' => 1000, 'CM' => 900, 'D' => 500, 'CD' => 400, 'C' => 100, 'XC' => 90, 'L' => 50, 'XL' => 40, 'X' => 10, 'IX' => 9, 'V' => 5, 'IV' => 4, 'I' => 1];
        $returnValue = '';
        while ($number > 0) {
            foreach ($map as $roman => $int) {
                if ($number >= $int) {
                    $number -= $int;
                    $returnValue .= $roman;
                    break;
                }
            }
        }

        return $returnValue;
    }

    public function gen_number_classic($modelx, $where, $whereYear, $colYear, $prefix)
    {
        $model = $this->genBaseQuery($modelx);
        if (! empty($where)) {
            $model->where($where);
        }
        if (! empty($whereYear)) {
            $model->whereYear($colYear, $whereYear);
        }
        $next = $model->get()->count();
        $next++;

        $adaY = strpos($prefix, '$');
        $jml = substr_count($prefix, '#');

        $count_dollar = substr_count($prefix, '$');

        $increment = str_repeat('#', $jml);
        $pos = strpos($prefix, '#');
        $nextno = str_pad($next, $jml, '0', STR_PAD_LEFT);
        $result = str_replace($increment, $nextno, $prefix);

        $result = str_replace(str_repeat('$', $count_dollar), substr($whereYear, $count_dollar == 2 ? 2 : 0, $count_dollar), $result);

        return $result;
    }
}
