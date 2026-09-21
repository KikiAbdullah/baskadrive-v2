<?php

namespace Tests\Feature;

use App\Http\Controllers\Auth\RegisterController;
use App\Models\DamageReport;
use App\Models\Fine;
use App\Models\InsuranceClaim;
use App\Models\Invoice;
use App\Models\Journal;
use App\Models\JournalDetail;
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
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
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
        config(['2fa.enabled' => false]);
        $this->travelTo(Carbon::parse('2026-09-17 12:00:00'));
        $this->seed(DatabaseSeeder::class);
    }

    protected function user(string $username): User
    {
        return User::where('username', $username)->firstOrFail();
    }

    private function assertErpRouteProtection(): void
    {
        $routes = collect(Route::getRoutes()->getRoutes())->filter(fn ($route) => str_starts_with($route->getActionName(), 'App\\Http\\Controllers\\Rental\\')
            || str_starts_with($route->getActionName(), 'App\\Http\\Controllers\\Master\\')
        );
        $this->assertNotEmpty($routes->all());

        foreach ($routes as $route) {
            $middleware = $route->middleware();
            $this->assertContains('auth', $middleware, $route->getName().' must inherit auth');
            $this->assertContains('two_factor', $middleware, $route->getName().' must inherit two_factor');
            $this->assertLessThan(array_search('two_factor', $middleware), array_search('auth', $middleware));
        }

        foreach ([
            'dashboard.index', 'master.customer.store', 'fleet.damage.index', 'accounting.journal.index', 'system.settings',
            'finance.index', 'finance.invoice.send-email', 'finance.fine.pay', 'finance.fine.waive',
            'rental.index', 'rental.create.store', 'rental.update', 'rental.confirm', 'rental.cancel',
            'rental.detail.fine.store', 'rental.detail.fine.pay', 'rental.detail.fine.waive',
            'rental.detail.invoice.generate', 'rental.detail.invoice.store', 'rental.detail.payment.store',
            'rental.detail.refund.store', 'rental.detail.extension.store', 'rental.detail.return.store',
            'report.export', 'report.export-pdf',
        ] as $name) {
            $this->assertTrue($routes->contains(fn ($route) => $route->getName() === $name), $name);
        }
    }

    private function enableFin01TwoFactor(string $username = 'supervisor'): User
    {
        $this->assertErpRouteProtection();
        $this->withMiddleware();
        config(['2fa.enabled' => true, '2fa.cookie_name' => 'fin01_test_2fa']);
        Http::fake();
        Mail::fake();
        $user = $this->user($username);
        $user->forceFill([
            'token_last_request' => now(),
            'token_2fa' => Hash::make('48261'),
            'token_2fa_expires_at' => now()->addMinutes(5),
            'two_factor_cookie_hash' => null,
            'two_factor_cookie_expires_at' => null,
        ])->save();
        $this->actingAs($user);

        return $user;
    }

    private function assertFin01ErpAccess(): void
    {
        foreach (['finance.index', 'rental.index'] as $name) {
            $this->get(route($name))->assertOk();
        }
        foreach (['finance.invoice.data', 'finance.fine.data', 'finance.payment.data', 'rental.data'] as $name) {
            $this->get(route($name))->assertOk()->assertJsonStructure(['data', 'recordsTotal'])
                ->assertJsonMissingPath('error');
        }
    }

    public function test_fin01_all_erp_routes_inherit_auth_and_two_factor_with_public_and_otp_exceptions(): void
    {
        $this->assertErpRouteProtection();

        foreach (['2fa.show', 'verifyTwoFactor'] as $name) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertNotNull($route);
            $this->assertContains('auth', $route->gatherMiddleware());
            $this->assertNotContains('two_factor', $route->gatherMiddleware());
        }

        $receipt = Route::getRoutes()->getByName('verify.receipt');
        $this->assertNotNull($receipt);
        $this->assertNotContains('auth', $receipt->gatherMiddleware());
        $this->assertNotContains('two_factor', $receipt->gatherMiddleware());
    }

    public function test_fin01_disabled_two_factor_allows_authorized_supervisor_erp_access(): void
    {
        $this->assertErpRouteProtection();
        $this->withMiddleware();
        $this->assertFalse(config('2fa.enabled'));
        $this->actingAs($this->user('supervisor'));
        $this->assertFin01ErpAccess();
    }

    public function test_fin01_valid_encrypted_cookie_allows_verified_supervisor_erp_access(): void
    {
        $user = $this->enableFin01TwoFactor();
        $token = 'fin01-synthetic-verified-client-token';
        $user->forceFill([
            'two_factor_cookie_hash' => Hash::make($token),
            'two_factor_cookie_expires_at' => now()->addHours(8),
        ])->save();
        $this->withCookie(config('2fa.cookie_name'), $token);
        $this->assertNotSame($token, $this->prepareCookiesForRequest()[config('2fa.cookie_name')]);
        $this->assertFin01ErpAccess();
        Http::assertNothingSent();
        Mail::assertNothingOutgoing();
    }

    public static function fin01UnverifiedCookies(): array
    {
        return [
            'no cookie' => ['missing'],
            'encrypted nonmatching cookie' => ['invalid'],
            'expired matching cookie' => ['expired'],
            'malformed encrypted cookie' => ['malformed'],
        ];
    }

    #[DataProvider('fin01UnverifiedCookies')]
    public function test_fin01_unverified_requests_redirect_to_otp_without_erp_mutations(string $cookieState): void
    {
        $user = $this->enableFin01TwoFactor();
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        $token = 'fin01-synthetic-client-token';
        $user->forceFill([
            'two_factor_cookie_hash' => Hash::make($token),
            'two_factor_cookie_expires_at' => $cookieState === 'expired' ? now()->subMinute() : now()->addHours(8),
        ])->save();
        if ($cookieState === 'invalid') {
            $this->withCookie(config('2fa.cookie_name'), 'fin01-nonmatching-client-token');
        } elseif ($cookieState === 'expired') {
            $this->withCookie(config('2fa.cookie_name'), $token);
        } elseif ($cookieState === 'malformed') {
            $this->withUnencryptedCookie(config('2fa.cookie_name'), 'fin01-not-an-encrypted-cookie');
        }

        $rental = Rental::query()->firstOrFail()->replicate()->unsetRelations();
        $rental->forceFill([
            'rental_code' => 'TEST-FIN01', 'status' => 'reserved', 'payment_status' => 'unpaid',
            'total_amount' => 1000, 'promo_id' => null,
        ])->save();
        $rental->customer->update(['email' => 'fin01@example.test', 'phone' => null]);
        $invoice = Invoice::create([
            'rental_id' => $rental->getKey(), 'invoice_number' => 'TEST-FIN01-INVOICE',
            'issue_date' => now(), 'due_date' => now()->addDays(7), 'sub_total' => 1000,
            'total_amount' => 1000, 'paid_amount' => 0, 'status' => 'draft',
        ]);
        $fine = Fine::create([
            'rental_id' => $rental->getKey(), 'fine_type' => 'late_return',
            'amount' => 100, 'status' => 'unpaid', 'issued_date' => now(),
        ]);
        $models = [Rental::class, Invoice::class, Fine::class, Payment::class, Refund::class, Journal::class, JournalDetail::class];
        $counts = array_map(fn ($model) => DB::table((new $model)->getTable())->count(), $models);
        $records = [$rental, $invoice, $fine, $rental->vehicle, $rental->customer];
        $states = array_map(fn ($model) => $model->fresh()->getRawOriginal(), $records);
        $otpState = $user->fresh()->getRawOriginal();

        $requests = [
            ['GET', route('finance.index'), []],
            ['GET', route('rental.index'), []],
            ['GET', route('finance.invoice.data'), []],
            ['GET', route('rental.data'), []],
            ['POST', route('finance.invoice.send-email', $invoice->getKey()), []],
            ['PUT', route('finance.fine.pay', $fine->getKey()), ['payment_method' => 'cash']],
            ['PUT', route('finance.fine.waive', $fine->getKey()), []],
            ['POST', route('rental.detail.payment.store', $rental->getKey()), ['amount' => 10, 'payment_method' => 'cash']],
            ['PUT', route('rental.cancel', $rental->getKey()), []],
        ];

        foreach ($requests as [$method, $url, $data]) {
            $this->{strtolower($method)}($url, $data)
                ->assertRedirect(route('2fa.show', ['redirect' => $url]));
            foreach ($models as $index => $model) {
                $this->assertDatabaseCount((new $model)->getTable(), $counts[$index]);
            }
            foreach ($records as $index => $model) {
                $this->assertSame($states[$index], $model->fresh()->getRawOriginal(), $method.' '.$url);
            }
            $this->assertSame($otpState, $user->fresh()->getRawOriginal());
            Http::assertNothingSent();
            Mail::assertNothingOutgoing();
        }
    }

    public function test_fin01_otp_get_and_valid_post_issue_cookie_that_unlocks_erp_without_loop(): void
    {
        $user = $this->enableFin01TwoFactor();
        $target = route('finance.index');
        $otpUrl = route('2fa.show', ['redirect' => $target]);
        $this->get($target)->assertRedirect($otpUrl);
        $this->get($otpUrl)->assertOk()->assertViewIs('auth.two_factor');
        $this->assertTrue(Hash::check('48261', $user->fresh()->token_2fa));
        $response = $this->post(route('verifyTwoFactor'), ['otp' => '48261', 'redirect' => $target]);
        $response->assertRedirect($target)->assertCookie(config('2fa.cookie_name'));
        $user->refresh();
        $this->assertNull($user->token_2fa);
        $this->assertNull($user->token_2fa_expires_at);
        $cookie = $response->getCookie(config('2fa.cookie_name'));
        $wireCookie = $response->getCookie(config('2fa.cookie_name'), false);
        $this->assertNotNull($cookie);
        $this->assertNotNull($wireCookie);
        $this->assertNotSame($cookie->getValue(), $wireCookie->getValue());
        $this->assertNotSame($cookie->getValue(), $user->two_factor_cookie_hash);
        $this->assertTrue(Hash::check($cookie->getValue(), $user->two_factor_cookie_hash));
        $this->assertTrue($user->two_factor_cookie_expires_at->equalTo(now()->addHours(8)));
        $this->withUnencryptedCookie(config('2fa.cookie_name'), $wireCookie->getValue());
        $this->assertFin01ErpAccess();
        $this->get($otpUrl)->assertRedirect('/');
        $this->get('/')->assertOk();
        Http::assertNothingSent();
        Mail::assertNothingOutgoing();
    }

    public function test_fin01_verified_viewer_remains_forbidden_from_finance_and_rental(): void
    {
        $user = $this->enableFin01TwoFactor('viewer');
        $token = 'fin01-synthetic-viewer-client-token';
        $user->forceFill([
            'two_factor_cookie_hash' => Hash::make($token),
            'two_factor_cookie_expires_at' => now()->addHours(8),
        ])->save();
        $this->withCookie(config('2fa.cookie_name'), $token);
        $this->get('/')->assertOk();
        foreach (['finance.index', 'rental.index', 'finance.invoice.data', 'rental.data'] as $name) {
            $this->get(route($name))->assertForbidden();
        }
        Http::assertNothingSent();
        Mail::assertNothingOutgoing();
    }

    public function test_fin01_guests_redirect_to_login_before_two_factor(): void
    {
        $this->assertErpRouteProtection();
        $this->withMiddleware();
        config(['2fa.enabled' => true]);
        Http::fake();
        Mail::fake();
        foreach (['finance.index', 'rental.index', 'finance.invoice.data', 'rental.data', '2fa.show'] as $name) {
            $this->get(route($name))->assertRedirect(route('login'));
        }
        $this->post(route('verifyTwoFactor'), ['otp' => '48261'])->assertRedirect(route('login'));
        Http::assertNothingSent();
        Mail::assertNothingOutgoing();
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
