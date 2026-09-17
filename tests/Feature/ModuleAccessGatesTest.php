<?php

namespace Tests\Feature;

use App\Http\Controllers\Auth\RegisterController;
use App\Models\DamageReport;
use App\Models\Fine;
use App\Models\InsuranceClaim;
use App\Models\Maintenance;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Rental;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Session\SymfonySessionDecorator;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ModuleAccessGatesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-09-17 12:00:00'));
        $this->seed(DatabaseSeeder::class);
    }

    protected function user(string $username): User
    {
        return User::where('username', $username)->firstOrFail();
    }

    public function test_viewer_cannot_access_operational_modules(): void
    {
        $viewer = $this->user('viewer');

        foreach (['/rental', '/fleet/damage', '/finance', '/accounting/journal'] as $url) {
            $this->actingAs($viewer)->get($url)->assertStatus(403);
        }
    }

    public function test_viewer_can_access_dashboard_and_report(): void
    {
        $this->actingAs($this->user('viewer'))->get('/')->assertStatus(200);
        $this->actingAs($this->user('viewer'))->get('/report')->assertStatus(200);
    }

    #[DataProvider('readOnlyRoles')]
    public function test_read_only_roles_cannot_export_or_see_export_links(string $role): void
    {
        $this->actingAs($this->user($role));

        foreach (self::reportTypes() as $tab => $type) {
            $this->get('/report?tab='.$tab)->assertOk()
                ->assertDontSee('id="btnExportCsv"', false)
                ->assertDontSee('id="btnExportPdf"', false)
                ->assertDontSee('/report/export/', false)
                ->assertDontSee('/report/export-pdf/', false);
            $this->get('/report/export/'.$type)->assertForbidden();
            $this->get('/report/export-pdf/'.$type)->assertForbidden();
            $this->travel(61)->seconds();
        }
    }

    public static function readOnlyRoles(): array
    {
        return [['viewer'], ['staff']];
    }

    private static function reportTypes(): array
    {
        return ['revenue' => 'revenue', 'fleet' => 'fleet-utilization', 'customers' => 'top-customers', 'claims' => 'claims', 'financial' => 'financial'];
    }

    public function test_report_rejects_invalid_dates_on_every_endpoint(): void
    {
        $this->actingAs($this->user('supervisor'));
        $cases = [
            [['start_date' => 'invalid', 'end_date' => '2026-09-17'], 'start_date'],
            [['start_date' => '2026-02-30', 'end_date' => '2026-09-17'], 'start_date'],
            [['start_date' => '2026-01-01', 'end_date' => 'invalid'], 'end_date'],
            [['start_date' => '2026-09-18', 'end_date' => '2026-09-17'], 'end_date'],
            [['start_date' => '2024-01-01', 'end_date' => '2025-01-01'], 'end_date'],
            [['start_date' => ['2026-01-01'], 'end_date' => '2026-09-17'], 'start_date'],
            [['start_date' => '2026-01-01', 'end_date' => ['2026-09-17']], 'end_date'],
            [['end_date' => '2025-12-31'], 'end_date'],
            [['start_date' => '2026-09-18'], 'end_date'],
            [['start_date' => '2025-01-01'], 'end_date'],
            [['end_date' => '2027-01-02'], 'end_date'],
        ];

        foreach ($cases as [$query, $field]) {
            foreach (['/report', '/report/get-data', '/report/export/revenue', '/report/export-pdf/revenue'] as $url) {
                $this->getJson($url.'?'.http_build_query($query))
                    ->assertUnprocessable()->assertJsonValidationErrors($field);
            }
            $this->travel(61)->seconds();
        }
    }

    public function test_default_dates_and_inclusive_366_day_range_are_accepted(): void
    {
        $this->actingAs($this->user('supervisor'));
        $this->get('/report')->assertOk()->assertViewHas('start', '2026-01-01')->assertViewHas('end', '2026-09-17');

        foreach ([['2024-01-01', '2024-12-31'], ['2026-09-17', '2026-09-17']] as [$start, $end]) {
            $query = http_build_query(['start_date' => $start, 'end_date' => $end]);
            $this->get('/report?'.$query)->assertOk()->assertViewHas('start', $start)->assertViewHas('end', $end);
            $this->getJson('/report/get-data?'.$query)->assertOk()->assertJsonStructure(['data', 'recordsTotal']);
            $this->csv('revenue', ['start_date' => $start, 'end_date' => $end]);
            Pdf::fake();
            $this->get('/report/export-pdf/revenue?'.$query)->assertOk();
            Pdf::assertRespondedWithPdf(function (PdfBuilder $pdf) use ($start, $end) {
                $this->assertSame('report.pdf', $pdf->viewName);
                $this->assertSame(Carbon::parse($start)->format('d/m/Y'), $pdf->viewData['start']);
                $this->assertSame(Carbon::parse($end)->format('d/m/Y'), $pdf->viewData['end']);

                return true;
            });
        }
    }

    public function test_sixth_invalid_export_is_throttled_across_csv_and_pdf(): void
    {
        $this->actingAs($this->user('supervisor'));

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $path = $attempt % 2 === 1 ? 'export' : 'export-pdf';
            $this->from('/report')->get('/report/'.$path.'/unknown')
                ->assertRedirect('/report')->assertSessionHasErrors()
                ->assertHeader('X-RateLimit-Limit', '5')
                ->assertHeader('X-RateLimit-Remaining', (string) (5 - $attempt));
        }

        $this->getJson('/report/export-pdf/unknown')->assertTooManyRequests()->assertHeader('Retry-After');
        $this->getJson('/report/export/unknown')->assertTooManyRequests();
        $this->get('/report')->assertOk();
        $this->actingAs($this->user('manager'))->from('/report')->get('/report/export/unknown')->assertRedirect('/report');
        $this->actingAs($this->user('supervisor'));
        $this->travel(61)->seconds();
        $this->from('/report')->get('/report/export-pdf/unknown')->assertRedirect('/report')->assertSessionHasErrors();
    }

    private function csv(string $type, array $query): array
    {
        $response = $this->get('/report/export/'.$type.'?'.http_build_query($query));
        $response->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->assertHeader('Content-Disposition', 'attachment; filename="'.$type.'_'.now()->format('Ymd').'.csv"');
        $content = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
        $stream = fopen('php://temp', 'w+');

        try {
            fwrite($stream, substr($content, 3));
            rewind($stream);
            $rows = [];
            while (($row = fgetcsv($stream, null, ';', '"', '')) !== false) {
                $rows[] = $row;
            }

            return $rows;
        } finally {
            fclose($stream);
        }
    }

    private function reportFixtures(): Rental
    {
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $template = Rental::query()->firstOrFail();
        $template->customer->update(['first_name' => 'Ayu', 'last_name' => 'Putri', 'customer_type' => 'corporate', 'company_name' => 'PT Uji; "Nusantara"']);
        $template->vehicle->update(['license_plate' => '001234']);
        $modelId = $template->vehicle->model_id;
        $brandId = DB::table('m_vehicle_model')->where('model_id', $modelId)->value('brand_id');
        DB::table('m_vehicle_model')->where('model_id', $modelId)->update(['model_name' => 'Test Model']);
        DB::table('m_brand')->where('brand_id', $brandId)->update(['brand_name' => 'Test Brand']);
        $rentals = [];

        foreach ([
            ['2030-01-01 00:00:00', 1, 1000.25, 'completed', false],
            ['2030-01-03 23:59:59', 1, 2000.50, 'cancelled', false],
            ['2030-02-28 23:59:59', 3, 3000.75, 'reserved', false],
            ['2030-01-02 12:00:00', 99, 99999, 'completed', true],
            ['2029-12-31 23:59:59', 10, 99999, 'completed', false],
            ['2030-03-01 00:00:00', 10, 99999, 'completed', false],
        ] as $index => [$date, $days, $amount, $status, $deleted]) {
            $rental = $template->replicate()->unsetRelations();
            $rental->forceFill([
                'rental_code' => 'TEST-REPORT-'.$index,
                'rental_start_date' => $date,
                'rental_end_date' => Carbon::parse($date)->addDays($days),
                'rental_days' => $days,
                'total_amount' => $amount,
                'status' => $status,
            ])->save();
            if ($deleted) {
                $rental->delete();
            }
            $rentals[] = $rental;
        }
        $rental = $rentals[0];

        foreach ([
            ['2030-01-01 00:00:00', 1000.25, 'completed', false],
            ['2030-01-31 23:59:59', 2000.50, 'completed', false],
            ['2030-02-28 23:59:59', 4000.75, 'completed', false],
            ['2030-01-10 12:00:00', 99999, 'pending', false],
            ['2030-02-10 12:00:00', 99999, 'refunded', false],
            ['2030-02-11 12:00:00', 99999, 'completed', true],
            ['2029-12-31 23:59:59', 99999, 'completed', false],
            ['2030-03-01 00:00:00', 99999, 'completed', false],
        ] as [$date, $amount, $status, $deleted]) {
            $payment = Payment::create(['rental_id' => $rental->getKey(), 'payment_date' => $date, 'amount' => $amount, 'payment_method' => 'cash', 'status' => $status]);
            if ($deleted) {
                $payment->delete();
            }
        }

        foreach ([['2030-01-15', 100.25], ['2030-02-15', 5000.50], ['2030-04-01', 25.25], ['2029-12-31', 99999]] as [$date, $cost]) {
            $maintenance = Maintenance::query()->firstOrFail()->replicate()->unsetRelations();
            $maintenance->forceFill(['actual_date' => $date, 'cost' => $cost])->save();
        }
        foreach ([['2030-01-16', 20.50, 'paid'], ['2030-02-16', 30.25, 'paid'], ['2030-01-17', 99999, 'unpaid'], ['2029-12-31', 99999, 'paid']] as [$date, $amount, $status]) {
            $fine = Fine::query()->firstOrFail()->replicate()->unsetRelations();
            $fine->forceFill(['rental_id' => $rental->getKey(), 'paid_date' => $date, 'amount' => $amount, 'status' => $status])->save();
        }
        foreach ([['2030-01-20', 50.25, 'processed', false], ['2030-02-20', 70.25, 'processed', false], ['2030-01-21', 99999, 'pending', false], ['2030-02-21', 99999, 'processed', true], ['2029-12-31', 99999, 'processed', false]] as [$date, $amount, $status, $deleted]) {
            $refund = Refund::create(['rental_id' => $rental->getKey(), 'refund_date' => $date, 'amount' => $amount, 'refund_type' => 'cancellation', 'status' => $status]);
            if ($deleted) {
                $refund->delete();
            }
        }

        foreach (['reported', 'assessment', 'repair_in_progress', 'repaired', 'claimed_insurance', 'written_off'] as $index => $status) {
            $damage = DamageReport::create([
                'rental_id' => $rental->getKey(), 'vehicle_id' => $rental->vehicle_id,
                'reported_date' => $index === 0 ? '2030-01-01 00:00:00' : '2030-02-28 23:59:59',
                'damage_type' => 'exterior', 'severity' => 'minor', 'status' => $status,
                'repair_cost_estimate' => 900.75, 'actual_repair_cost' => 800.25,
            ]);
            if ($index > 0) {
                InsuranceClaim::create([
                    'rental_id' => $rental->getKey(), 'damage_id' => $damage->getKey(),
                    'claim_number' => 'TEST-CLAIM-'.$index, 'insurance_provider' => 'Test Insurance',
                    'policy_number' => 'TEST-POLICY', 'claim_date' => '2030-02-28',
                    'claim_amount' => 500.25, 'status' => 'approved',
                ]);
            }
        }
        $outside = $damage->replicate()->unsetRelations();
        $outside->reported_date = '2030-03-01 00:00:00';
        $outside->save();

        return $rental;
    }

    public function test_all_report_data_csv_and_pdf_match_synthetic_sqlite_totals(): void
    {
        $this->reportFixtures();
        $this->actingAs($this->user('supervisor'));
        $filters = ['start_date' => '2030-01-01', 'end_date' => '2030-02-28'];
        $expected = [
            'revenue' => [
                ['Periode', 'Pendapatan', 'Jumlah Pembayaran'],
                ['Februari 2030', '4000,75', '1'], ['Januari 2030', '3000,75', '2'],
            ],
            'fleet' => [
                ['Plat', 'Kendaraan', 'Jml Sewa', 'Hari', 'Utilisasi', 'Pendapatan'],
                ['001234', 'Test Brand Test Model', '3', '5', '8,00%', '6001,50'],
            ],
            'customers' => [
                ['Pelanggan', 'Jml Sewa', 'Total Belanja'],
                ['Ayu Putri (PT Uji; "Nusantara")', '3', '6001,50'],
            ],
            'claims' => [
                ['Kendaraan', 'Jenis', 'Severity', 'Status', 'Klaim', 'Nilai Klaim'],
                ['001234', 'exterior', 'minor', 'reported', "'-", '0,00'],
                ...array_map(fn ($status) => ['001234', 'exterior', 'minor', $status, 'approved', '500,25'], ['assessment', 'repair_in_progress', 'repaired', 'claimed_insurance', 'written_off']),
            ],
            'financial' => [
                ['Periode', 'Pendapatan', 'Beban', 'Laba'],
                ['Februari 2030', '4000,75', '5101,00', '-1100,25'],
                ['Januari 2030', '3000,75', '171,00', '2829,75'],
            ],
        ];

        foreach (self::reportTypes() as $tab => $type) {
            $this->travel(61)->seconds();
            $page = $this->get('/report?'.http_build_query($filters + ['tab' => $tab]));
            $page->assertOk()->assertSee('id="btnExportCsv"', false)->assertSee('id="btnExportPdf"', false);
            foreach (['report.export', 'report.export-pdf'] as $route) {
                $page->assertSee('href="'.e(route($route, ['type' => $type] + $filters + ['status' => ''])).'"', false);
            }
            $data = $this->getJson('/report/get-data?'.http_build_query($filters + ['tab' => $tab, 'length' => 100]))
                ->assertOk()->assertJsonMissingPath('error')->assertJsonCount(count($expected[$tab]) - 1, 'data')
                ->assertJsonPath('recordsTotal', count($expected[$tab]) - 1)
                ->assertJsonPath('recordsFiltered', count($expected[$tab]) - 1)->json('data');
            $this->assertSame($expected[$tab], $this->csv($type, $filters));

            if ($tab === 'revenue') {
                $this->assertSame(['2030-02', '2030-01'], array_column($data, 'period'));
                $this->assertEquals([4000.75, 3000.75], array_column($data, 'revenue'));
                $this->assertEquals([1, 2], array_column($data, 'payments_count'));
                $page->assertViewHas('totalRevenue', fn ($total) => (float) $total === 7001.5);
            } elseif ($tab === 'fleet') {
                $this->assertSame('001234', $data[0]['license_plate']);
                $this->assertSame('Test Brand Test Model', $data[0]['vehicle_name']);
                $this->assertEquals(3, $data[0]['total_rentals']);
                $this->assertSame(5, $data[0]['total_days']);
                $this->assertSame('8%', $data[0]['utilization']);
                $this->assertEquals(6001.5, $data[0]['total_revenue']);
            } elseif ($tab === 'customers') {
                $this->assertSame('Ayu Putri (PT Uji; "Nusantara")', html_entity_decode($data[0]['customer_name'], ENT_QUOTES));
                $this->assertSame('Corporate', $data[0]['customer_type']);
                $this->assertEquals(3, $data[0]['total_rentals']);
                $this->assertEquals(6001.5, $data[0]['total_spend']);
                $this->assertSame('28/02/2030', $data[0]['last_rental']);
            } elseif ($tab === 'claims') {
                $this->assertSame(['reported', 'assessment', 'repair_in_progress', 'repaired', 'claimed_insurance', 'written_off'], array_column($data, 'status'));
                $this->assertEquals(array_fill(0, 6, 800.25), array_column($data, 'repair_cost'));
                $this->assertSame(null, $data[0]['claim_amount']);
                $this->assertEquals(array_fill(0, 5, 500.25), array_slice(array_column($data, 'claim_amount'), 1));
            } else {
                $this->assertSame(['Februari 2030', 'Januari 2030'], array_column($data, 'period'));
                $this->assertEquals([4000.75, 3000.75], array_column($data, 'income'));
                $this->assertEquals([5101, 171], array_column($data, 'expense'));
                $this->assertEquals([-1100.25, 2829.75], array_column($data, 'profit'));
                $page->assertViewHas('income', fn ($value) => (float) $value === 7001.5)
                    ->assertViewHas('expense', fn ($value) => (float) $value === 5272.0)
                    ->assertViewHas('profit', fn ($value) => (float) $value === 1729.5);
            }

            Pdf::fake();
            $this->get('/report/export-pdf/'.$type.'?'.http_build_query($filters))->assertOk();
            Pdf::assertRespondedWithPdf(function (PdfBuilder $pdf) use ($tab, $type, $expected) {
                $this->assertSame('report.pdf', $pdf->viewName);
                $this->assertSame('Laporan-'.$type.'-'.now()->format('Ymd').'.pdf', $pdf->downloadName);
                $this->assertSame('01/01/2030', $pdf->viewData['start']);
                $this->assertSame('28/02/2030', $pdf->viewData['end']);
                $this->assertSame($expected[$tab][0], $pdf->viewData['rows'][0]);
                $this->assertCount(count($expected[$tab]), $pdf->viewData['rows']);
                $html = $pdf->getHtml();
                $this->assertStringContainsString('Periode: 01/01/2030 s.d. 28/02/2030', $html);
                foreach (array_slice($expected[$tab], 1) as $rowIndex => $row) {
                    foreach ($row as $column => $cell) {
                        $kind = $pdf->viewData['columnTypes'][$column];
                        $raw = $pdf->viewData['rows'][$rowIndex + 1][$column];
                        if ($kind === 'money') {
                            $this->assertEquals((float) str_replace(',', '.', $cell), (float) $raw);
                            $display = 'Rp '.number_format((float) $raw, 2, ',', '.');
                        } elseif ($kind === 'percent') {
                            $this->assertEquals((float) str_replace(',', '.', $cell), (float) $raw);
                            $display = $cell;
                        } else {
                            $display = $cell === "'-" ? '-' : $cell;
                            $this->assertSame($display, (string) $raw);
                        }
                        $this->assertStringContainsString('<td>'.e($display).'</td>', $html);
                    }
                }
                if ($tab === 'fleet') {
                    $this->assertStringContainsString('inklusif', $pdf->viewData['note']);
                }

                return true;
            });
        }
    }

    public function test_fleet_uses_inclusive_days_all_statuses_and_caps_at_100_percent(): void
    {
        $this->reportFixtures();
        $this->actingAs($this->user('supervisor'));

        foreach ([['2030-01-01', '2030-01-03', '67%'], ['2030-01-01', '2030-01-01', '100%']] as [$start, $end, $utilization]) {
            $filters = ['start_date' => $start, 'end_date' => $end];
            $this->getJson('/report/get-data?'.http_build_query($filters + ['tab' => 'fleet']))
                ->assertOk()->assertJsonPath('data.0.utilization', $utilization);
            $rows = $this->csv('fleet-utilization', $filters);
            $this->assertSame($utilization === '67%' ? '67,00%' : '100,00%', $rows[1][4]);
        }
        $filters = ['start_date' => '2030-02-28', 'end_date' => '2030-02-28'];
        $this->getJson('/report/get-data?'.http_build_query($filters + ['tab' => 'fleet']))
            ->assertOk()->assertJsonPath('data.0.total_days', 3)->assertJsonPath('data.0.utilization', '100%');
        $this->assertSame('100,00%', $this->csv('fleet-utilization', $filters)[1][4]);
    }

    public function test_claim_status_filters_are_identical_in_data_csv_pdf_and_links(): void
    {
        $this->reportFixtures();
        $this->actingAs($this->user('supervisor'));

        foreach (['reported', 'assessment', 'repair_in_progress', 'repaired', 'claimed_insurance', 'written_off'] as $status) {
            $this->travel(61)->seconds();
            $filters = ['start_date' => '2030-01-01', 'end_date' => '2030-02-28', 'status' => $status];
            $this->get('/report?'.http_build_query($filters + ['tab' => 'claims']))->assertOk()
                ->assertSee('href="'.e(route('report.export', ['type' => 'claims'] + $filters)).'"', false)
                ->assertSee('href="'.e(route('report.export-pdf', ['type' => 'claims'] + $filters)).'"', false);
            $response = $this->getJson('/report/get-data?'.http_build_query($filters + ['tab' => 'claims']))
                ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.status', $status);
            $this->assertSame(trim(view('components.damage-status-badge', ['status' => $status])->render()), trim($response->json('data.0.status_badge')));
            if ($status !== 'reported') {
                $this->assertStringContainsString('Disetujui', $response->json('data.0.claim_status'));
            }
            $rows = $this->csv('claims', $filters);
            $this->assertCount(2, $rows);
            $this->assertSame($status, $rows[1][3]);
            Pdf::fake();
            $this->get('/report/export-pdf/claims?'.http_build_query($filters))->assertOk();
            Pdf::assertRespondedWithPdf(function (PdfBuilder $pdf) use ($status) {
                $this->assertCount(2, $pdf->viewData['rows']);
                $this->assertSame($status, $pdf->viewData['rows'][1][3]);

                return true;
            });
        }
    }

    public function test_financial_report_includes_expense_only_months(): void
    {
        $this->reportFixtures();
        $this->actingAs($this->user('supervisor'));
        $filters = ['start_date' => '2030-04-01', 'end_date' => '2030-04-30'];
        $this->getJson('/report/get-data?'.http_build_query($filters + ['tab' => 'financial']))
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.period', 'April 2030')
            ->assertJsonPath('data.0.income', 0)->assertJsonPath('data.0.expense', 25.25)->assertJsonPath('data.0.profit', -25.25);
        $this->assertSame([
            ['Periode', 'Pendapatan', 'Beban', 'Laba'], ['April 2030', '0,00', '25,25', '-25,25'],
        ], $this->csv('financial', $filters));
    }

    public function test_pdf_allows_2000_rows_rejects_2001_and_csv_remains_uncapped(): void
    {
        $rental = Rental::query()->firstOrFail();
        $record = [
            'rental_id' => $rental->getKey(), 'vehicle_id' => $rental->vehicle_id,
            'reported_date' => '2031-01-01 12:00:00', 'damage_type' => 'exterior',
            'severity' => 'minor', 'status' => 'reported',
        ];
        foreach (array_chunk(array_fill(0, 2000, $record), 100) as $chunk) {
            DB::table('tr_damage_report')->insert($chunk);
        }
        $this->actingAs($this->user('supervisor'));
        $filters = ['start_date' => '2031-01-01', 'end_date' => '2031-01-01'];
        Pdf::fake();
        $this->get('/report/export-pdf/claims?'.http_build_query($filters))->assertOk();
        Pdf::assertRespondedWithPdf(function (PdfBuilder $pdf) {
            $this->assertCount(2001, $pdf->viewData['rows']);

            return true;
        });
        DB::table('tr_damage_report')->insert($record);
        Pdf::shouldReceive('view')->never();
        $this->getJson('/report/export-pdf/claims?'.http_build_query($filters))
            ->assertUnprocessable()->assertJsonValidationErrors('type');
        $this->assertCount(2002, $this->csv('claims', $filters));
    }

    public function test_staff_can_access_rental_but_not_accounting(): void
    {
        $staff = $this->user('staff');
        $this->actingAs($staff)->get('/rental')->assertStatus(200);
        $this->actingAs($staff)->get('/accounting/journal')->assertStatus(403);
    }

    public function test_supervisor_and_manager_can_access_accounting(): void
    {
        $this->actingAs($this->user('supervisor'))->get('/accounting/journal')->assertStatus(200);
        $this->actingAs($this->user('manager'))->get('/accounting/journal')->assertStatus(200);
    }

    public function test_public_registration_assigns_viewer_role(): void
    {
        config(['app.registration_enabled' => true]);

        $req = Request::create('/register', 'POST', [
            'name' => 'Pendatang Baru',
            'email' => 'baru@contoh.test',
            'password' => 'PasswordKuat88',
            'password_confirmation' => 'PasswordKuat88',
        ]);
        $session = app('session.store');
        $session->start();
        $req->setSession(new SymfonySessionDecorator($session));
        app()->instance('request', $req);

        (new RegisterController)->register($req);

        $baru = User::where('email', 'baru@contoh.test')->first();
        $this->assertNotNull($baru);
        $this->assertTrue($baru->hasRole('VIEWER'), 'registrasi publik otomatis role VIEWER');
        $this->assertTrue($baru->can('dashboard_view'));
        $this->assertFalse($baru->can('rental_view'), 'VIEWER tidak boleh dapat akses operasional');
    }

    public function test_registration_controller_aborts_when_flag_off(): void
    {
        config(['app.registration_enabled' => false]);

        $req = Request::create('/register', 'POST', [
            'name' => 'X', 'email' => 'x@contoh.test',
            'password' => 'PasswordKuat88', 'password_confirmation' => 'PasswordKuat88',
        ]);
        app()->instance('request', $req);

        $this->expectException(HttpException::class);
        (new RegisterController)->register($req);
    }
}
