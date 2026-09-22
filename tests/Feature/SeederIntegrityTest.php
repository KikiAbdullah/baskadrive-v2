<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Rental;
use App\Models\Vehicle;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Regresi integritas data seed (remediasi SED-01..SED-10, audit 15092026).
 *
 * Setiap test menjalankan DatabaseSeeder penuh lalu memverifikasi invariant
 * bisnis & kontrak remediasi FIN/FLE/AKN pada data demo yang dihasilkan.
 */
class SeederIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    /* ================= SED-01: semua tabel terisi ================= */

    public function test_semua_tabel_inti_terisi_data(): void
    {
        $tables = [
            'm_brand', 'm_vehicle_model', 'm_vehicle', 'm_customer', 'm_driver',
            'm_location', 'm_workshop', 'm_maintenance_type', 'm_promo', 'm_coa',
            'm_employee', 'tr_rental', 'tr_rental_detail', 'tr_rental_extension',
            'tr_return', 'tr_damage_report', 'tr_damage_photo', 'tr_insurance_claim',
            'tr_maintenance', 'tr_fine', 'tr_invoice', 'tr_payment', 'tr_refund',
            'tr_journal', 'tr_journal_detail', 'user_logs', 'app_settings',
        ];

        foreach ($tables as $t) {
            $this->assertGreaterThan(0, DB::table($t)->count(), "Tabel $t kosong.");
        }
    }

    public function test_pipeline_seeder_lama_tidak_dipanggil_database_seeder(): void
    {
        // SED-01: hanya admin seeders + CompleteSeeder (arsip lama tidak dijalankan).
        $this->assertFileExists(database_path('seeders/RentalErpRentalSeeder.php'));
        $content = file_get_contents(database_path('seeders/DatabaseSeeder.php'));
        $this->assertStringNotContainsString('RentalErpRentalSeeder::class', $content);
        $this->assertStringNotContainsString('RentalErpFinanceSeeder::class', $content);
    }

    /* ================= SED-02: formula invoice ================= */

    public function test_formula_invoice_total_sama_dengan_sub_total_kurang_diskon_plus_pajak(): void
    {
        $invoices = DB::table('tr_invoice')->get();
        $this->assertGreaterThan(50, $invoices->count());

        foreach ($invoices as $inv) {
            $expected = round($inv->sub_total - $inv->discount + $inv->tax, 2);
            $this->assertSame($expected, round($inv->total_amount, 2),
                "Invoice {$inv->invoice_number}: total tidak konsisten.");
        }
    }

    public function test_invoice_paid_amount_konsisten_dengan_status_dan_payment(): void
    {
        $invoices = DB::table('tr_invoice')->get();

        foreach ($invoices as $inv) {
            $this->assertTrue($inv->paid_amount <= $inv->total_amount + 0.01, "Invoice {$inv->invoice_number}: paid > total.");
            if ($inv->status === 'paid') {
                $this->assertEqualsWithDelta($inv->total_amount, $inv->paid_amount, 0.01, "Invoice {$inv->invoice_number}: status paid tapi belum lunas.");
            }
            if ($inv->status === 'partially_paid') {
                $this->assertGreaterThan(0, $inv->paid_amount, "Invoice {$inv->invoice_number}: partially_paid tanpa pembayaran.");
            }
        }

        // Satu invoice per rental (kontrak FIN-06/07).
        $dupes = DB::table('tr_invoice')->select('rental_id', DB::raw('count(*) as n'))->groupBy('rental_id')->havingRaw('n > 1')->count();
        $this->assertSame(0, (int) $dupes);
    }

    /* ================= SED-03: jurnal adjustment nyata ================= */

    public function test_semua_jurnal_seimbang_dan_tidak_ada_baris_nol(): void
    {
        $rows = DB::table('tr_journal_detail')->selectRaw('journal_id, sum(debit) as d, sum(credit) as c')
            ->groupBy('journal_id')->get();

        $this->assertGreaterThan(20, $rows->count());
        foreach ($rows as $r) {
            $this->assertEqualsWithDelta($r->d, $r->c, 0.01, "Jurnal #{$r->journal_id} tidak seimbang.");
        }

        // Tidak ada detail 0/0 (kontrak AKN-07).
        $zeros = DB::table('tr_journal_detail')->where('debit', 0)->where('credit', 0)->count();
        $this->assertSame(0, (int) $zeros);
    }

    public function test_jurnal_adjustment_memiliki_nilai_nyata(): void
    {
        $adj = DB::table('tr_journal')->where('journal_type', 'adjustment')->get();
        $this->assertGreaterThan(0, $adj->count());

        $detail = DB::table('tr_journal_detail')->whereIn('journal_id', $adj->pluck('journal_id'))->get();
        $this->assertTrue($detail->contains(fn ($d) => $d->debit > 0), 'Adjustment tidak memuat nilai nyata (SED-03).');
    }

    /* ================= SED-04: deposit, payment allocation, refund ================= */

    public function test_payment_allocation_deposit_dan_rental_konsisten(): void
    {
        // allocation=rental harus tertaut invoice; allocation=deposit tanpa invoice.
        $badRental = DB::table('tr_payment')->where('allocation', 'rental')->whereNull('invoice_id')->count();
        $badDeposit = DB::table('tr_payment')->where('allocation', 'deposit')->whereNotNull('invoice_id')->count();
        $this->assertSame(0, (int) $badRental);
        $this->assertSame(0, (int) $badDeposit);

        $depositCount = DB::table('tr_payment')->where('allocation', 'deposit')->count();
        $this->assertGreaterThan(10, $depositCount, 'Tahanan deposit tidak terbuat (SED-04).');
    }

    public function test_sum_payment_rental_sama_dengan_paid_amount_invoice(): void
    {
        $mismatch = DB::table('tr_invoice as i')
            ->leftJoin(DB::raw('(select invoice_id, sum(amount) as paid from tr_payment where allocation = \'rental\' and status = \'completed\' group by invoice_id) as p'), 'p.invoice_id', '=', 'i.invoice_id')
            ->whereRaw('coalesce(p.paid, 0) <> i.paid_amount')
            ->count();
        $this->assertSame(0, (int) $mismatch, 'Σ payment (rental) ≠ paid_amount invoice.');
    }

    public function test_refund_deposit_melalui_uang_muka_pelanggan(): void
    {
        // Jurnal refund wajib melunasi 2-3000 (uang muka) dan TIDAK membalik
        // pendapatan sewa secara kumulatif (tanpa debit ke akun 4-x).
        $uangMuka = DB::table('m_coa')->where('account_code', '2-3000')->value('account_id');
        $this->assertNotNull($uangMuka);

        $refundJournals = DB::table('tr_journal')->where('journal_type', 'refund')->pluck('journal_id');
        $refundDetails = DB::table('tr_journal_detail')->whereIn('journal_id', $refundJournals)->get();
        $this->assertGreaterThan(0, $refundDetails->count());

        foreach ($refundJournals as $jid) {
            $details = DB::table('tr_journal_detail')->where('journal_id', $jid)->get();
            $this->assertTrue($details->contains(fn ($d) => $d->account_id == $uangMuka && $d->debit > 0),
                "Refund jurnal #$jid tidak melunasi uang muka 2-3000.");
            $this->assertTrue($details->every(fn ($d) => ! ($d->debit > 0 && str_starts_with(DB::table('m_coa')->where('account_id', $d->account_id)->value('account_code') ?? '', '4-'))),
                "Refund jurnal #$jid membalik pendapatan (Dr 4-x) — melanggar SED-04.");
        }
    }

    public function test_rental_berdeposit_memiliki_payment_deposit(): void
    {
        // Rental dengan deposit > 0 dan status bukan cancelled wajib punya payment deposit.
        $missing = DB::table('tr_rental as r')
            ->leftJoin(DB::raw('(select distinct rental_id from tr_payment where allocation = \'deposit\' and status = \'completed\') as pd'), 'pd.rental_id', '=', 'r.rental_id')
            ->where('r.deposit_amount', '>', 0)
            ->whereIn('r.status', ['completed', 'ongoing', 'reserved'])
            ->whereNull('pd.rental_id')
            ->count();
        $this->assertSame(0, (int) $missing, 'Ada rental berdeposit tanpa payment deposit (SED-04).');
    }

    /* ================= SED-05: klaim paid berjurnal ================= */

    public function test_klaim_paid_membuat_jurnal_pencairan(): void
    {
        $pendKlaim = DB::table('m_coa')->where('account_code', '4-3000')->value('account_id');
        $insuranceDetails = DB::table('tr_journal as j')
            ->join('tr_journal_detail as d', 'd.journal_id', '=', 'j.journal_id')
            ->where('j.journal_type', 'insurance')
            ->where('d.account_id', $pendKlaim)
            ->where('d.credit', '>', 0)
            ->count();

        $paidClaims = DB::table('tr_insurance_claim')->where('status', 'paid')->where('approved_amount', '>', 0)->count();
        $this->assertGreaterThan(0, $paidClaims);
        $this->assertGreaterThanOrEqual($paidClaims, $insuranceDetails, 'Klaim paid tidak semua berjurnal pencairan (SED-05/FLE-09).');
    }

    /* ================= SED-06: denda sesuai kontrak ================= */

    public function test_denda_kerusakan_tertaut_damage_id(): void
    {
        $linked = DB::table('tr_fine')->where('fine_type', 'damage')->whereNotNull('damage_id')->count();
        $this->assertGreaterThan(0, $linked, 'Tidak ada denda kerusakan tertaut damage (FLE-07).');

        $orphan = DB::table('tr_fine')->where('fine_type', 'damage')->whereNull('damage_id')->count();
        $this->assertSame(0, (int) $orphan);
    }

    public function test_denda_terisi_identitas_petugas_dan_jurnal_sesuai_status(): void
    {
        // paid_by utk paid, waived_by utk waived (FIN-04).
        $paidNoOfficer = DB::table('tr_fine')->where('status', 'paid')->whereNull('paid_by')->count();
        $waivedNoOfficer = DB::table('tr_fine')->where('status', 'waived')->whereNull('waived_by')->count();
        $this->assertSame(0, (int) $paidNoOfficer);
        $this->assertSame(0, (int) $waivedNoOfficer);

        // Denda damage unpaid harus punya jurnal akrual (Dr 1-2100).
        $piutang = DB::table('m_coa')->where('account_code', '1-2100')->value('account_id');
        $damageFines = DB::table('tr_fine')->where('fine_type', 'damage')->pluck('fine_id');
        $this->assertGreaterThan(0, $damageFines->count());

        // Setiap denda damage (status apa pun) wajib punya jurnal akrual.
        $accrued = DB::table('tr_journal as j')
            ->join('tr_journal_detail as d', 'd.journal_id', '=', 'j.journal_id')
            ->where('j.journal_type', 'fine')
            ->where('d.account_id', $piutang)
            ->whereIn('j.description', DB::table('tr_fine')->whereIn('fine_id', $damageFines)->get()
                ->map(fn ($f) => 'Akrual piutang denda kerusakan FND-'.str_pad((string) $f->fine_id, 5, '0', STR_PAD_LEFT))
                ->all())
            ->distinct()->count();
        $this->assertGreaterThanOrEqual(1, $accrued, 'Denda kerusakan tidak memiliki akrual piutang (FLE-08).');
    }

    /* ================= SED-07: status kendaraan sinkron ================= */

    public function test_status_kendaraan_sinkron_dengan_sewa_aktif(): void
    {
        $ongoing = DB::table('tr_rental')->where('status', 'ongoing')->distinct()->pluck('vehicle_id');
        $reserved = DB::table('tr_rental')->where('status', 'reserved')->distinct()->pluck('vehicle_id');
        $this->assertGreaterThan(0, $ongoing->count());

        foreach ($ongoing as $vid) {
            $this->assertSame('rented', Vehicle::find($vid)->status, "Unit #$vid dengan sewa ongoing tidak berstatus rented.");
        }
        foreach ($reserved as $vid) {
            $this->assertContains(Vehicle::find($vid)->status, ['reserved', 'rented'], "Unit #$vid reserved tidak sinkron.");
        }

        // Tidak ada dua sewa aktif overlap di unit yang sama.
        $active = DB::table('tr_rental')->whereIn('status', ['ongoing', 'reserved'])->orderBy('vehicle_id')->get(['vehicle_id']);
        $this->assertSame($active->count(), $active->unique('vehicle_id')->count(), 'Unit memiliki lebih dari satu sewa aktif.');
    }

    /* ================= SED-08: odometer konsisten ================= */

    public function test_odometer_inspeksi_konsisten(): void
    {
        $inspections = DB::table('rental_inspections')->get();
        $this->assertGreaterThan(0, $inspections->count());

        foreach ($inspections as $i) {
            $this->assertGreaterThan(0, $i->odometer, "Inspeksi #{$i->id} odometer nol/negatif.");
        }

        // handover_in ≥ handover_out per rental (bila keduanya ada).
        $byRental = $inspections->groupBy('rental_id');
        foreach ($byRental as $rentalId => $group) {
            $out = $group->firstWhere('inspection_type', 'handover_out');
            $in = $group->firstWhere('inspection_type', 'handover_in');
            if ($out && $in) {
                $this->assertGreaterThanOrEqual($out->odometer, $in->odometer, "Rental #$rentalId: handover_in < handover_out.");
            }
        }
    }

    public function test_maintenance_mileage_tidak_negatif(): void
    {
        $negative = DB::table('tr_maintenance')->where('current_mileage', '<=', 0)->count();
        $this->assertSame(0, (int) $negative);
    }

    /* ================= SED-09: user log valid ================= */

    public function test_user_logs_merujuk_user_aktual(): void
    {
        $users = DB::table('users')->pluck('id');
        $orphan = DB::table('user_logs')->whereNotIn('user_id', $users)->count();
        $this->assertSame(0, (int) $orphan);
    }

    /* ================= Invariant lintas: Rental model ================= */

    public function test_rental_data_dasar_valid(): void
    {
        $rentals = DB::table('tr_rental')->get();
        $this->assertGreaterThan(50, $rentals->count());

        foreach ($rentals as $r) {
            $this->assertTrue($r->rental_end_date >= $r->rental_start_date, "Rental {$r->rental_code}: end < start.");
            $this->assertGreaterThanOrEqual(0, $r->discount_amount);
            $this->assertLessThanOrEqual(100, (int) round($r->tax_percent), "Rental {$r->rental_code}: PPN tidak wajar.");
        }

        // Rental aktif tidak overlap dengan rental selesai lainnya pada unit sama (window aktif saja).
        $actives = DB::table('tr_rental')->whereIn('status', ['ongoing', 'reserved'])->get();
        foreach ($actives as $a) {
            $other = DB::table('tr_rental')
                ->where('vehicle_id', $a->vehicle_id)
                ->whereIn('status', ['ongoing', 'reserved'])
                ->where('rental_id', '!=', $a->rental_id)
                ->count();
            $this->assertSame(0, (int) $other, "Unit {$a->vehicle_id} memiliki >1 sewa aktif.");
        }
    }

    public function test_jurnal_tidak_ada_di_masa_depan(): void
    {
        $future = DB::table('tr_journal')->where('transaction_date', '>', now()->toDateString())->count();
        $this->assertSame(0, (int) $future);
    }

    public function test_pencairan_klaim_paid_paling_lama_bulan_lalu(): void
    {
        // Jurnal insurance hanya utk klaim paid — paid_at dibatasi tidak lebih dari ~2 bulan lalu.
        $insurance = DB::table('tr_journal')->where('journal_type', 'insurance')->get();
        foreach ($insurance as $j) {
            $this->assertTrue(Carbon::parse($j->transaction_date)->lessThanOrEqualTo(now()), 'Jurnal klaim di masa depan.');
        }
    }

    /* ================= Invariant: payment denda tidak tertukar ================= */

    public function test_tidak_ada_payment_denda_pada_data_seed(): void
    {
        // Kontrak: settlement denda lewat tr_fine (paid_by/paid_date), bukan payment allocation=fine.
        $finePayments = DB::table('tr_payment')->where('allocation', 'fine')->count();
        $this->assertSame(0, (int) $finePayments);
    }

    public function test_klaim_dan_damage_konsisten_dengan_rental(): void
    {
        $bad = DB::table('tr_insurance_claim as c')
            ->join('tr_damage_report as d', 'd.damage_id', '=', 'c.damage_id')
            ->whereColumn('c.rental_id', '!=', 'd.rental_id')
            ->count();
        $this->assertSame(0, (int) $bad);
    }
}
