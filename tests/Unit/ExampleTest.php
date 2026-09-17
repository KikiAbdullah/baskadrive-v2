<?php

namespace Tests\Unit;

use App\Support\ReportFormat;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ExampleTest extends TestCase
{
    #[DataProvider('formattedCells')]
    public function test_formats_cells_by_column_type(mixed $value, string $kind, string $display, string $csv): void
    {
        $this->assertSame($display, ReportFormat::cell($value, $kind));
        $this->assertSame($csv, ReportFormat::cell($value, $kind, true));
    }

    public static function formattedCells(): array
    {
        return [
            'money decimals' => ['1234567.89', 'money', 'Rp 1.234.567,89', '1234567,89'],
            'negative money' => [-1234.56, 'money', 'Rp -1.234,56', '-1234,56'],
            'money zero' => [0, 'money', 'Rp 0,00', '0,00'],
            'money rounding' => ['12.345', 'money', 'Rp 12,35', '12,35'],
            'money trailing zero' => [12.5, 'money', 'Rp 12,50', '12,50'],
            'integer' => [1234, 'integer', '1.234', '1234'],
            'percent' => [33.33, 'percent', '33,33%', '33,33%'],
            'leading zero identifier' => ['001234', 'text', '001234', '001234'],
            'large identifier' => ['12345678901234567890', 'text', '12345678901234567890', '12345678901234567890'],
            'numeric text' => ['1234.50', 'text', '1234.50', '1234.50'],
            'null' => [null, 'money', '', ''],
            'empty' => ['', 'text', '', ''],
        ];
    }

    #[DataProvider('formulaCells')]
    public function test_escapes_formula_like_csv_text_only(string $text): void
    {
        $this->assertSame($text, ReportFormat::cell($text));
        $this->assertSame("'".$text, ReportFormat::cell($text, 'text', true));
    }

    public static function formulaCells(): array
    {
        return [
            'equals' => ['=1+1'],
            'plus' => ['+123'],
            'minus numeric identifier' => ['-00123'],
            'at' => ['@SUM(A1:A2)'],
            'space prefix' => ['  =1+1'],
            'tab' => ["\t123"],
            'carriage return' => ["\r123"],
            'newline' => ["\n123"],
            'whitespace prefix' => [" \t@SUM(A1:A2)"],
        ];
    }

    public function test_preserves_safe_text_and_embedded_formula_characters(): void
    {
        foreach (['PT Aman', 'INV-001', 'a+b@example.test', '  ordinary', '"quoted";value'] as $text) {
            $this->assertSame($text, ReportFormat::cell($text, 'text', true));
        }
    }

    public function test_month_labels_are_indonesian_independent_of_global_locale(): void
    {
        $locale = Carbon::getLocale();
        Carbon::setLocale('en');

        try {
            foreach (['01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April', '05' => 'Mei', '06' => 'Juni', '07' => 'Juli', '08' => 'Agustus', '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember'] as $month => $name) {
                $this->assertSame($name.' 2030', ReportFormat::month('2030-'.$month));
            }
            $this->assertSame('en', Carbon::getLocale());
        } finally {
            Carbon::setLocale($locale);
        }
    }
}
