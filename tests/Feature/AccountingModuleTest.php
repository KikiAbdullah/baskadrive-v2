<?php

namespace Tests\Feature;

use App\Models\Coa;
use App\Models\Journal;
use App\Models\JournalDetail;
use App\Models\User;
use App\Services\AccountingService;
use App\Support\AppSettings;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Regresi langsung modul Akuntansi — remediasi AKN-01..AKN-12 (audit 15092026):
 * ekspor CSV, jurnal manual, saldo normal buku besar, agregasi SQL, filter enum,
 * rentang statement, neraca lintas tahun, dan klasifikasi arus kas.
 */
class AccountingModuleTest extends TestCase
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

    private function acc(string $code): int
    {
        return Coa::where('account_code', $code)->value('account_id');
    }

    private function postManual(array $overrides = [])
    {
        $payload = array_merge([
            'transaction_date' => '2026-09-17',
            'reference_number' => 'JRN-TEST-1',
            'description' => 'Jurnal uji regresi',
            'entries' => [
                ['account_id' => $this->acc('1-1100'), 'debit' => 500000, 'credit' => 0],
                ['account_id' => $this->acc('4-1100'), 'debit' => 0, 'credit' => 500000],
            ],
        ], $overrides);

        return $this->actingAs($this->user('manager'))
            ->from(route('accounting.journal.index'))
            ->post(route('accounting.manual-journal.store'), $payload);
    }

    // ============================================================
    // AKN-01 / AKN-09 / AKN-02: export hidup + CSV standar modul Laporan
    // ============================================================

    public function test_journal_export_downloads_with_report_standard(): void
    {
        $journalId = Journal::firstOrFail()->journal_id;

        $res = $this->actingAs($this->user('manager'))
            ->get(route('accounting.journal.export', $journalId));

        $res->assertOk();
        $res->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $content = $res->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
        $this->assertStringContainsString(';', $content);
        // AKN-02: nominal dua desimal (sen tidak lagi dibulatkan).
        $this->assertStringContainsString(',00', $content);
        $this->assertStringContainsString('Kode Akun', $content);
    }

    public function test_export_permission_and_button_visibility(): void
    {
        $journalId = Journal::firstOrFail()->journal_id;

        // SUPERVISOR punya accounting_view tetapi TIDAK punya accounting_export (AKN-11).
        $this->actingAs($this->user('supervisor'))
            ->get(route('accounting.journal.export', $journalId))
            ->assertForbidden();

        $this->actingAs($this->user('staff'))
            ->get(route('accounting.journal.export', $journalId))
            ->assertForbidden();

        // Tombol EXPORT CSV disembunyikan bagi pemegang view-only.
        $menu = $this->actingAs($this->user('supervisor'))
            ->get(route('accounting.journal.button-option', ['id' => $journalId]))
            ->assertOk()
            ->json('view');

        $this->assertStringNotContainsString('EXPORT CSV', $menu);

        // Manager (berizin) melihat tombol dan berhasil mengunduh.
        $this->actingAs($this->user('manager'))
            ->get(route('accounting.journal.button-option', ['id' => $journalId]))
            ->assertOk()
            ->json('view');

        $this->actingAs($this->user('manager'))
            ->get(route('accounting.journal.export', $journalId))
            ->assertOk();
    }

    public function test_export_is_throttled(): void
    {
        $journalId = Journal::firstOrFail()->journal_id;

        for ($i = 0; $i < 30; $i++) {
            $this->actingAs($this->user('manager'))
                ->get(route('accounting.journal.export', $journalId))
                ->assertOk();
        }

        $this->actingAs($this->user('manager'))
            ->get(route('accounting.journal.export', $journalId))
            ->assertTooManyRequests();
    }

    // ============================================================
    // AKN-07: validasi entri jurnal manual per baris
    // ============================================================

    public function test_manual_journal_rejects_row_with_both_sides(): void
    {
        $before = JournalDetail::count();

        // Total "seimbang" (500/500) tetapi baris pertama dua sisi.
        $this->postManual([
            'entries' => [
                ['account_id' => $this->acc('1-1100'), 'debit' => 500, 'credit' => 300],
                ['account_id' => $this->acc('4-1100'), 'debit' => 0, 'credit' => 200],
            ],
        ])->assertSessionHasErrors();

        $this->assertSame($before, JournalDetail::count());
    }

    public function test_manual_journal_rejects_zero_value_row(): void
    {
        $before = JournalDetail::count();

        $this->postManual([
            'entries' => [
                ['account_id' => $this->acc('1-1100'), 'debit' => 0, 'credit' => 0],
                ['account_id' => $this->acc('4-1100'), 'debit' => 100, 'credit' => 100],
            ],
        ])->assertSessionHasErrors();

        $this->assertSame($before, JournalDetail::count());
    }

    public function test_manual_journal_rejects_negative_overflow_and_long_reference(): void
    {
        $before = JournalDetail::count();

        $this->postManual(['entries' => [
            ['account_id' => $this->acc('1-1100'), 'debit' => -100, 'credit' => 0],
            ['account_id' => $this->acc('4-1100'), 'debit' => 0, 'credit' => -100],
        ]])->assertSessionHasErrors();

        // Melebihi kapasitas DECIMAL(15,2).
        $this->postManual(['entries' => [
            ['account_id' => $this->acc('1-1100'), 'debit' => 99999999999999.0, 'credit' => 0],
            ['account_id' => $this->acc('4-1100'), 'debit' => 0, 'credit' => 99999999999999.0],
        ]])->assertSessionHasErrors();

        // Kolom DB varchar(50) unique — harus ditolak validasi, bukan error SQL.
        $this->postManual(['reference_number' => str_repeat('X', 51)])
            ->assertSessionHasErrors();

        $this->assertSame($before, JournalDetail::count());
    }

    public function test_manual_journal_rejects_parent_and_inactive_accounts(): void
    {
        $before = JournalDetail::count();

        // 1-1000 adalah akun induk (punya anak 1-1100/1-1200).
        $this->postManual(['entries' => [
            ['account_id' => $this->acc('1-1000'), 'debit' => 100000, 'credit' => 0],
            ['account_id' => $this->acc('4-1100'), 'debit' => 0, 'credit' => 100000],
        ]])->assertSessionHasErrors();

        // Akun dinonaktifkan tidak boleh menerima mutasi via request langsung.
        Coa::where('account_code', '5-1100')->update(['is_active' => false]);
        $this->postManual(['entries' => [
            ['account_id' => $this->acc('5-1100'), 'debit' => 100000, 'credit' => 0],
            ['account_id' => $this->acc('1-1100'), 'debit' => 0, 'credit' => 100000],
        ]])->assertSessionHasErrors();

        $this->assertSame($before, JournalDetail::count());
    }

    public function test_manual_journal_stores_balanced_entry(): void
    {
        $before = Journal::count();

        $this->postManual()
            ->assertRedirect(route('accounting.journal.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame($before + 1, Journal::count());
        $journal = Journal::where('reference_number', 'JRN-TEST-1')->firstOrFail();

        $this->assertSame('manual', $journal->journal_type);
        $this->assertSame(500000.0, (float) $journal->details->sum('debit'));
        $this->assertSame(500000.0, (float) $journal->details->sum('credit'));
    }

    // ============================================================
    // AKN-08: validasi gagal tidak meracuni koneksi untuk request berikutnya
    // ============================================================

    public function test_invalid_manual_journal_does_not_poison_next_request(): void
    {
        $this->postManual([
            'entries' => [
                ['account_id' => $this->acc('1-1100'), 'debit' => 500, 'credit' => 0],
                ['account_id' => $this->acc('4-1100'), 'debit' => 0, 'credit' => 100],
            ],
        ])->assertSessionHasErrors();

        // Pada koneksi yang sama, submit valid berikutnya harus tetap berhasil.
        $this->postManual()
            ->assertRedirect(route('accounting.journal.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('tr_journal', ['reference_number' => 'JRN-TEST-1']);
    }

    public function test_manual_journal_requires_write_permission(): void
    {
        $this->actingAs($this->user('staff'))
            ->get(route('accounting.manual-journal.create'))
            ->assertForbidden();

        $this->actingAs($this->user('staff'))
            ->post(route('accounting.manual-journal.store'), [])
            ->assertForbidden();
    }

    // ============================================================
    // AKN-06: filter enum & rentang tanggal statement
    // ============================================================

    public function test_journal_and_ledger_filters_follow_enum(): void
    {
        $manager = $this->user('manager');

        // 'auto'/'closing' bukan anggota enum journal_type; 'revenue' bukan enum account_type.
        $this->actingAs($manager)->getJson(route('accounting.journal.data', ['type' => 'auto']))->assertStatus(422);
        $this->actingAs($manager)->getJson(route('accounting.journal.data', ['type' => 'manual']))->assertOk();
        $this->actingAs($manager)->getJson(route('accounting.ledger.data', ['type' => 'revenue']))->assertStatus(422);
        $this->actingAs($manager)->getJson(route('accounting.ledger.data', ['type' => 'income']))->assertOk();
    }

    public function test_statement_rejects_invalid_dates_and_inverted_range(): void
    {
        $this->actingAs($this->user('manager'))
            ->get(route('accounting.statement.income', ['start_date' => 'bukan-tanggal']))
            ->assertSessionHasErrors();

        $this->actingAs($this->user('manager'))
            ->get(route('accounting.statement.income', ['start_date' => '2026-05-01', 'end_date' => '2026-01-01']))
            ->assertSessionHasErrors();

        $this->actingAs($this->user('manager'))
            ->get(route('accounting.statement.balance', ['end_date' => '2026-09-17']))
            ->assertOk();

        $this->actingAs($this->user('manager'))
            ->get(route('accounting.statement.cashflow', ['start_date' => '2026-01-01', 'end_date' => '2026-09-17']))
            ->assertOk();
    }

    public function test_index_dropdowns_are_synced_with_enums(): void
    {
        $this->actingAs($this->user('manager'))
            ->get(route('accounting.journal.index'))
            ->assertOk()
            ->assertSee('value="insurance"', false)
            ->assertDontSee('value="auto"', false);

        $this->actingAs($this->user('manager'))
            ->get(route('accounting.ledger.index'))
            ->assertOk()
            ->assertSee('value="income"', false)
            ->assertDontSee('value="revenue"', false);
    }

    // ============================================================
    // AKN-02 / AKN-12: formatter dua desimal & tanggal Indonesia
    // ============================================================

    public function test_journal_data_shows_two_decimal_amounts(): void
    {
        $res = $this->actingAs($this->user('manager'))->getJson(route('accounting.journal.data'));

        $res->assertOk();
        $this->assertStringContainsString(',00', $res->getContent());
    }

    public function test_journal_show_renders_indonesian_date(): void
    {
        $journal = Journal::firstOrFail();

        $this->actingAs($this->user('manager'))
            ->get(route('accounting.journal.show', $journal->journal_id))
            ->assertOk()
            ->assertSee($journal->transaction_date->locale('id')->translatedFormat('d F Y'), false);
    }

    // ============================================================
    // AKN-04: saldo buku besar sesuai saldo normal
    // ============================================================

    public function test_ledger_shows_credit_normal_accounts_positive(): void
    {
        $res = $this->actingAs($this->user('manager'))->getJson(route('accounting.ledger.data', ['length' => 100]));
        $res->assertOk();

        $rows = collect($res->json('data'));

        foreach ($rows as $row) {
            $debit = (float) JournalDetail::where('account_id', $row['account_id'])->sum('debit');
            $credit = (float) JournalDetail::where('account_id', $row['account_id'])->sum('credit');

            // Kolom akun induk tampil "—" (tidak bermutasi).
            if (Coa::find($row['account_id'])->children()->exists()) {
                $this->assertSame('—', $row['balance']);

                continue;
            }

            $expected = AppSettings::money(
                AccountingService::signedBalance(Coa::find($row['account_id'])->account_type, $debit, $credit)
            );
            $this->assertSame($expected, $row['balance'], 'Saldo akun '.$row['account_id']);
        }

        // Akun pendapatan hasil seed wajib tampil positif (saldo normal kredit).
        $incomeRow = $rows->firstWhere('account_id', $this->acc('4-1100'));
        $this->assertNotNull($incomeRow);
        $this->assertStringNotContainsString('-', $incomeRow['balance']);
    }

    public function test_ledger_detail_running_balance_ends_at_net_movement(): void
    {
        $accountId = $this->acc('1-1100');

        $res = $this->actingAs($this->user('manager'))
            ->get(route('accounting.ledger.detail', $accountId))
            ->assertOk();

        $debit = (float) JournalDetail::where('account_id', $accountId)->sum('debit');
        $credit = (float) JournalDetail::where('account_id', $accountId)->sum('credit');

        $res->assertSee(AppSettings::money($debit - $credit), false);
    }

    // ============================================================
    // AKN-05: agregasi SQL, bukan pemindaian detail di PHP
    // ============================================================

    public function test_journal_data_uses_sql_aggregation_not_full_detail_scan(): void
    {
        DB::enableQueryLog();
        $res = $this->actingAs($this->user('manager'))->getJson(route('accounting.journal.data', ['length' => 10]));
        $queries = collect(DB::getQueryLog());
        DB::disableQueryLog();

        $res->assertOk();

        // Tidak boleh ada query "SELECT * FROM tr_journal_detail ..." (pemuatan detail penuh).
        $fullDetailScan = $queries->contains(function ($q) {
            return str_contains($q['query'], 'tr_journal_detail')
                && str_starts_with(strtolower(ltrim($q['query'])), 'select *');
        });
        $this->assertFalse($fullDetailScan);

        // withSum menghasilkan agregasi sum(debit) di subquery — kutip kolom dihapus dulu.
        $this->assertTrue($queries->contains(function ($q) {
            $sql = strtolower(str_replace(['"', "'"], '', $q['query']));

            return str_contains($sql, 'sum(tr_journal_detail.debit)')
                && str_contains($sql, 'sum(tr_journal_detail.credit)');
        }));
    }

    // ============================================================
    // AKN-03: neraca tetap berimbang lintas tahun (laba akumulasi)
    // ============================================================

    public function test_balance_sheet_balances_with_prior_year_profit(): void
    {
        $svc = app(AccountingService::class);
        $since = Carbon::create(2000, 1, 1)->startOfDay();
        $asOf = Carbon::parse('2026-09-17')->endOfDay();

        $beforeAccumulated = (float) $svc->incomeStatement($since, $asOf)['net_profit'];
        $beforeYtd = (float) $svc->incomeStatement(Carbon::create(2026, 1, 1)->startOfDay(), $asOf)['net_profit'];

        // Tahun lalu: pendapatan 1.000.000.
        $svc->post('2025-06-30', 'PRIOR-1', 'Pendapatan tahun lalu', 'manual', [
            ['account' => $this->acc('1-1100'), 'debit' => 1000000, 'credit' => 0],
            ['account' => $this->acc('4-1100'), 'debit' => 0, 'credit' => 1000000],
        ]);

        // Tahun ini: beban 400.000.
        $svc->post('2026-09-15', 'CUR-1', 'Beban tahun ini', 'manual', [
            ['account' => $this->acc('5-1100'), 'debit' => 400000, 'credit' => 0],
            ['account' => $this->acc('1-1100'), 'debit' => 0, 'credit' => 400000],
        ]);

        $report = $svc->balanceSheet($asOf);

        // Invarian fundamental: Aset = Kewajiban + Ekuitas (termasuk laba akumulasi).
        $this->assertEqualsWithDelta(
            $report['total_assets'],
            $report['total_liabilities'] + $report['total_equity_with_profit'],
            0.01
        );

        $this->assertEqualsWithDelta($beforeAccumulated + 600000.0, $report['accumulated_profit'], 0.01);
        $this->assertEqualsWithDelta($beforeYtd - 400000.0, $report['current_period_profit'], 0.01);
        $this->assertEqualsWithDelta(
            $report['accumulated_profit'] - $report['current_period_profit'],
            $report['prior_years_profit'],
            0.01
        );
    }

    // ============================================================
    // AKN-10: klasifikasi arus kas berbasis kode akun lawan
    // ============================================================

    public function test_cash_flow_classifies_by_counter_account_code(): void
    {
        $svc = app(AccountingService::class);
        $range = [Carbon::parse('2026-09-01')->startOfDay(), Carbon::parse('2026-09-17')->endOfDay()];

        $before = $svc->cashFlow(...$range);

        // Pelunasan piutang sewa (1-2100) → OPERATING, bukan investing.
        $svc->post('2026-09-10', 'CF-REC', 'Pelunasan piutang sewa', 'payment', [
            ['account' => $this->acc('1-1100'), 'debit' => 250000, 'credit' => 0],
            ['account' => $this->acc('1-2100'), 'debit' => 0, 'credit' => 250000],
        ]);

        // Pembelian aset tetap (1-3100) → INVESTING.
        $svc->post('2026-09-11', 'CF-FA', 'Pembelian kendaraan', 'manual', [
            ['account' => $this->acc('1-3100'), 'debit' => 5000000, 'credit' => 0],
            ['account' => $this->acc('1-1100'), 'debit' => 0, 'credit' => 5000000],
        ]);

        // Utang jangka panjang (2-2000) → FINANCING.
        $svc->post('2026-09-12', 'CF-LTD', 'Penarikan utang jangka panjang', 'manual', [
            ['account' => $this->acc('1-1200'), 'debit' => 3000000, 'credit' => 0],
            ['account' => $this->acc('2-2000'), 'debit' => 0, 'credit' => 3000000],
        ]);

        // Setoran modal (3-1000) → FINANCING.
        $svc->post('2026-09-12', 'CF-EQ', 'Setoran modal', 'manual', [
            ['account' => $this->acc('1-1200'), 'debit' => 1000000, 'credit' => 0],
            ['account' => $this->acc('3-1000'), 'debit' => 0, 'credit' => 1000000],
        ]);

        $after = $svc->cashFlow(...$range);

        $this->assertEqualsWithDelta(
            ($before['operating'] ?? 0) + 250000.0,
            $after['operating'],
            0.01
        );
        $this->assertEqualsWithDelta(
            ($before['investing'] ?? 0) - 5000000.0,
            $after['investing'],
            0.01
        );
        $this->assertEqualsWithDelta(
            ($before['financing'] ?? 0) + 4000000.0,
            $after['financing'],
            0.01
        );
    }
}
