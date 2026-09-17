<?php

namespace App\Support;

use Carbon\Carbon;

class ReportFormat
{
    public static function cell(mixed $value, string $kind = 'text', bool $csv = false): string
    {
        if ($value === null) {
            return '';
        }

        if (is_numeric($value)) {
            $formatted = match ($kind) {
                'money' => ($csv ? '' : 'Rp ').number_format((float) $value, 2, ',', $csv ? '' : '.'),
                'integer' => number_format((float) $value, 0, ',', $csv ? '' : '.'),
                'percent' => number_format((float) $value, 2, ',', $csv ? '' : '.').'%',
                default => null,
            };

            if ($formatted !== null) {
                return $formatted;
            }
        }

        $text = (string) $value;

        if ($csv && preg_match('/^\s*[=+@\-\t\r\n]/u', $text)) {
            return "'".$text;
        }

        return $text;
    }

    public static function month(string $period): string
    {
        return Carbon::createFromFormat('!Y-m', $period)->locale('id')->translatedFormat('F Y');
    }
}
