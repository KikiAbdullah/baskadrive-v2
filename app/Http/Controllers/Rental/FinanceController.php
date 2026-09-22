<?php

namespace App\Http\Controllers\Rental;

use App\Http\Controllers\Controller;
use App\Mail\InvoiceMail;
use App\Models\Fine;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\BatchPaymentService;
use App\Services\FineSettlementService;
use App\Support\AppSettings;
use App\Support\PdfDocument;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\LaravelPdf\Facades\Pdf;
use Symfony\Component\Mailer\Exception\TransportException;

class FinanceController extends Controller
{
    /** Status enum per entitas untuk filter DataTables (FIN-15). */
    private const INVOICE_STATUSES = ['draft', 'sent', 'partially_paid', 'paid', 'overdue', 'cancelled'];

    private const FINE_STATUSES = ['unpaid', 'paid', 'waived'];

    private const PAYMENT_STATUSES = ['pending', 'completed', 'failed', 'refunded'];

    /** Batas nominal sesuai DECIMAL(12,2): 9.999.999.999,99 (FIN-10). */
    private const MONEY_MAX = '9999999999.99';

    public function __construct()
    {
        $this->middleware('auth');
    }

    // ============================================================
    // SATU HALAMAN: INVOICE / DENDA / PEMBAYARAN
    // ============================================================

    public function index(Request $request)
    {
        // FIN-15: tab divalidasi scalar (array input ditolak), fallback ke invoice.
        $tab = is_string($request->input('tab')) ? $request->input('tab') : 'invoice';

        $tabs = [
            'invoice' => 'Invoice',
            'fine' => 'Denda',
            'payment' => 'Pembayaran',
        ];

        if (! array_key_exists($tab, $tabs)) {
            $tab = 'invoice';
        }

        return view('finance.index')->with([
            'title' => 'Keuangan',
            'subtitle' => 'Invoice, Denda & Pembayaran',
            'tab' => $tab,
            'tabs' => $tabs,
        ]);
    }

    // ============================================================
    // INVOICE
    // ============================================================

    public function invoiceData(Request $request)
    {
        // FIN-15: filter status enum-validasi, bukan input mentah.
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(self::INVOICE_STATUSES)],
        ]);

        $query = Invoice::with(['rental.customer', 'rental.vehicle']);

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        return datatables()->of($query)
            ->addColumn('invoice_number', fn ($i) => $i->invoice_number ?? '-')
            ->addColumn('rental_code', fn ($i) => $i->rental?->rental_code ?? '-')
            ->addColumn('customer', fn ($i) => $i->rental?->customer?->full_name ?? '-')
            ->addColumn('issue_date', fn ($i) => $i->issue_date?->format('d/m/Y') ?? '-')
            ->addColumn('due_date', fn ($i) => $i->due_date?->format('d/m/Y') ?? '-')
            ->addColumn('total', fn ($i) => AppSettings::money($i->total_amount))
            ->addColumn('paid', fn ($i) => AppSettings::money($i->paid_amount))
            ->addColumn('remaining', fn ($i) => AppSettings::money(($i->total_amount ?? 0) - ($i->paid_amount ?? 0)))
            ->addColumn('status_badge', fn ($i) => view('components.invoice-status-badge', ['status' => $i->status])->render())
            ->rawColumns(['status_badge'])
            ->toJson();
    }

    public function invoiceButtonOption(Request $request)
    {
        try {
            $id = $request->get('id');
            $item = Invoice::findOrFail(is_numeric($id) ? (int) $id : $id);

            return response()->json([
                'status' => true,
                'view' => view('finance.invoice.button_option')->with(['item' => $item])->render(),
            ]);
        } catch (Exception $e) {
            return response()->json(['status' => false, 'msg' => 'Invoice tidak ditemukan.'], 404);
        }
    }

    public function invoiceShow($id)
    {
        $item = Invoice::with([
            'rental.customer',
            'rental.vehicle.model.brand',
            'rental.details',
            'rental.pickupLocation',
            'rental.returnLocation',
            'payments',
        ])->findOrFail($id);

        return view('finance.invoice.show')->with([
            'title' => 'Detail Invoice',
            'subtitle' => 'Invoice '.($item->invoice_number ?? ''),
            'item' => $item,
        ]);
    }

    private function companySettings(): array
    {
        return AppSettings::all();
    }

    public function invoicePrint($id)
    {
        $item = Invoice::with([
            'rental.customer',
            'rental.vehicle.model.brand',
            'rental.driver',
            'rental.details',
            'rental.pickupLocation',
            'rental.returnLocation',
            'payments',
        ])->findOrFail($id);

        return PdfDocument::download(
            'finance.invoice.print',
            ['item' => $item, 'settings' => $this->companySettings()],
            'Invoice-'.($item->invoice_number ?? $item->invoice_id)
        );
    }

    public function invoiceSendEmail(Request $request, $id)
    {
        try {
            // FIN-11: transisi status dievaluasi pada state TERKINI (fresh query + lock),
            // bukan model yang dimuat sebelum render/pengiriman.
            $item = Invoice::where('invoice_id', $id)->lockForUpdate()->firstOrFail();

            $recipient = $item->rental?->customer?->email;
            if (! $recipient) {
                return response()->json(['status' => false, 'msg' => 'Pelanggan tidak memiliki alamat email. Perbarui data pelanggan terlebih dahulu.']);
            }

            if (in_array($item->status, ['cancelled'], true)) {
                return response()->json(['status' => false, 'msg' => 'Invoice berstatus cancelled tidak dapat dikirim.']);
            }

            $settings = $this->companySettings();

            // FIN-11: file temp UNIK per operasi (id + timestamp + unik) sehingga dua
            // pengiriman paralel tidak saling menimpa/menghapus file bersama.
            Storage::disk('local')->makeDirectory('pdf-tmp');
            $pdfPath = Storage::disk('local')->path('pdf-tmp/Invoice-'.($item->invoice_number ?? $item->invoice_id).'-'.uniqid('', true).'.pdf');

            Pdf::view('finance.invoice.print', ['item' => $item, 'settings' => $settings])
                ->format('a4')
                ->save($pdfPath);

            try {
                Mail::to($recipient)->send(new InvoiceMail($item, $pdfPath, $settings));
            } finally {
                if (is_file($pdfPath)) {
                    @unlink($pdfPath);
                }
            }

            // FIN-11: draft → sent hanya jika status TERKINI masih draft.
            if ($item->status === 'draft') {
                $item->update(['status' => 'sent']);
            }

            return response()->json(['status' => true, 'msg' => 'Invoice PDF berhasil dikirim ke '.$recipient]);
        } catch (ModelNotFoundException $e) {
            return response()->json(['status' => false, 'msg' => 'Invoice tidak ditemukan.'], 404);
        } catch (TransportException $e) {
            // FIN-12: pesan operasional, detail teknis hanya di log server.
            report($e);

            return response()->json(['status' => false, 'msg' => 'Gagal mengirim email: server surat tidak dapat dihubungi. Coba lagi atau hubungi administrator.'], 502);
        } catch (Exception $e) {
            report($e);

            return response()->json(['status' => false, 'msg' => 'Gagal mengirim email. Silakan coba lagi.'], 500);
        }
    }

    // ============================================================
    // FINE (DENDA)
    // ============================================================

    public function fineData(Request $request)
    {
        // FIN-15: status enum-validasi.
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(self::FINE_STATUSES)],
        ]);

        $query = Fine::with(['rental.customer', 'rental.vehicle', 'returnRecord']);

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        return datatables()->of($query)
            ->addColumn('rental_code', fn ($f) => $f->rental?->rental_code ?? '-')
            ->addColumn('customer', fn ($f) => $f->rental?->customer?->full_name ?? '-')
            ->addColumn('vehicle', fn ($f) => $f->rental?->vehicle?->license_plate ?? '-')
            ->addColumn('fine_type', fn ($f) => ucfirst(str_replace('_', ' ', $f->fine_type ?? '-')))
            ->addColumn('amount', fn ($f) => AppSettings::money($f->amount))
            ->addColumn('issued_date', fn ($f) => $f->issued_date?->format('d/m/Y') ?? '-')
            ->addColumn('status_badge', fn ($f) => view('components.fine-status-badge', ['status' => $f->status])->render())
            ->rawColumns(['status_badge'])
            ->toJson();
    }

    public function fineButtonOption(Request $request)
    {
        try {
            $id = $request->get('id');
            $item = Fine::findOrFail(is_numeric($id) ? (int) $id : $id);

            return response()->json([
                'status' => true,
                'view' => view('finance.fine.button_option')->with(['item' => $item])->render(),
            ]);
        } catch (Exception $e) {
            return response()->json(['status' => false, 'msg' => 'Denda tidak ditemukan.'], 404);
        }
    }

    /**
     * Bayar denda melalui service settlement terpusat (FIN-02/FIN-03).
     * Finance dan Sewa kini menjamin perilaku sama: state machine, lock dalam
     * transaksi, Payment allocation=fine, dan jurnal pendapatan denda.
     */
    public function finePay(Request $request, $id)
    {
        // FIN-10: metode & referensi divalidasi sesuai enum/panjang skema.
        $validated = $request->validate([
            'payment_method' => ['nullable', Rule::in(['cash', 'bank_transfer', 'credit_card', 'debit_card', 'e_wallet', 'other'])],
            'reference_number' => ['nullable', 'string', 'max:50'],
        ]);

        try {
            app(FineSettlementService::class)->pay((int) $id, null, $validated);

            return response()->json(['status' => true, 'msg' => 'Denda berhasil dibayar.']);
        } catch (ValidationException $e) {
            throw $e;
        } catch (ModelNotFoundException $e) {
            // Denda tidak ada ATAU sudah settle (predicate unpaid pada lock) —
            // keduanya dilaporkan sama agar tidak bocor state internal.
            return response()->json(['status' => false, 'msg' => 'Denda tidak ditemukan atau sudah diselesaikan.'], 409);
        } catch (Exception $e) {
            report($e);

            return response()->json(['status' => false, 'msg' => 'Gagal membayar denda: '.$e->getMessage()], 422);
        }
    }

    /**
     * Bebaskan denda melalui service settlement terpusat (FIN-02).
     */
    public function fineWaive(Request $request, $id)
    {
        try {
            app(FineSettlementService::class)->waive((int) $id);

            return response()->json(['status' => true, 'msg' => 'Denda dibebaskan.']);
        } catch (ModelNotFoundException $e) {
            return response()->json(['status' => false, 'msg' => 'Denda tidak ditemukan atau sudah diselesaikan.'], 409);
        } catch (Exception $e) {
            report($e);

            return response()->json(['status' => false, 'msg' => 'Gagal membebaskan denda: '.$e->getMessage()], 422);
        }
    }

    // ============================================================
    // PAYMENT (PEMBAYARAN)
    // ============================================================

    public function paymentData(Request $request)
    {
        // FIN-15: status enum-validasi.
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(self::PAYMENT_STATUSES)],
        ]);

        $query = Payment::with(['invoice', 'rental.customer', 'rental.vehicle']);

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        return datatables()->of($query)
            ->addColumn('receipt_number', fn ($p) => 'PAY-'.str_pad($p->payment_id, 5, '0', STR_PAD_LEFT))
            ->addColumn('invoice_number', fn ($p) => $p->invoice?->invoice_number ?? '-')
            ->addColumn('rental_code', fn ($p) => $p->rental?->rental_code ?? '-')
            ->addColumn('customer', fn ($p) => $p->rental?->customer?->full_name ?? '-')
            ->addColumn('payment_date', fn ($p) => $p->payment_date?->format('d/m/Y') ?? '-')
            ->addColumn('amount', fn ($p) => AppSettings::money($p->amount))
            ->addColumn('method', fn ($p) => ucfirst($p->payment_method ?? '-'))
            ->addColumn('status_badge', fn ($p) => view('components.payment-status-badge', ['status' => $p->status])->render())
            ->rawColumns(['status_badge'])
            ->toJson();
    }

    public function paymentButtonOption(Request $request)
    {
        try {
            $id = $request->get('id');
            $item = Payment::findOrFail(is_numeric($id) ? (int) $id : $id);

            return response()->json([
                'status' => true,
                'view' => view('finance.payment.button_option')->with(['item' => $item])->render(),
            ]);
        } catch (Exception $e) {
            return response()->json(['status' => false, 'msg' => 'Pembayaran tidak ditemukan.'], 404);
        }
    }

    // ============================================================
    // BATCH PAYMENT MULTI-INVOICE (butir 2.5 audit_12092026)
    // ============================================================

    /**
     * Halaman pembayaran borongan: daftar invoice yang masih punya sisa tagihan,
     * dikelompokkan per pelanggan (target: pelanggan korporat).
     */
    public function batchPaymentCreate()
    {
        // Invoice dengan sisa tagihan, urut per pelanggan (batch korporat biasanya
        // menyatukan banyak sewa satu pelanggan).
        $pendingInvoices = Invoice::with(['rental.customer'])
            ->whereIn('status', ['sent', 'partially_paid', 'overdue'])
            ->whereColumn('paid_amount', '<', 'total_amount')
            ->whereHas('rental.customer')
            ->get()
            ->sortBy(fn ($i) => [$i->rental?->customer?->full_name, $i->due_date])
            ->values();

        return view('finance.payment.batch')->with([
            'title' => 'Pembayaran Borongan',
            'subtitle' => 'Satu Pembayaran untuk Banyak Invoice (Korporat)',
            'pendingInvoices' => $pendingInvoices,
        ]);
    }

    /**
     * Simpan pembayaran borongan via service terpusat — atomik per batch.
     */
    public function batchPaymentStore(Request $request)
    {
        try {
            $validated = $request->validate([
                'invoices' => ['required', 'array', 'min:1', 'max:50'],
                'invoices.*.invoice_id' => ['required', 'integer', 'distinct'],
                'invoices.*.amount' => ['required', 'numeric', 'min:0.01', 'max:9999999999.99'],
                'payment_method' => ['required', Rule::in(['cash', 'bank_transfer', 'credit_card', 'debit_card', 'e_wallet', 'other'])],
                'reference_number' => ['nullable', 'string', 'max:50'],
                'notes' => ['nullable', 'string', 'max:500'],
            ], [
                'invoices.required' => 'Pilih minimal satu invoice beserta nominalnya.',
                'invoices.*.invoice_id.distinct' => 'Terdapat invoice yang dipilih dua kali.',
            ]);

            $result = app(BatchPaymentService::class)->payMany(
                $validated['invoices'],
                $validated['payment_method'],
                $validated['reference_number'] ?? null,
                $validated['notes'] ?? null
            );

            return response()->json([
                'status' => true,
                'msg' => 'Pembayaran borongan berhasil dicatat ('.count($result['payments']).' invoice, total Rp '.number_format($result['total'], 2, ',', '.').').',
                'data' => ['group_id' => $result['group_id'], 'receipt_number' => $result['receipt_number'] ?? null],
            ]);
        } catch (ValidationException $e) {
            throw $e;
        } catch (Exception $e) {
            report($e);

            return response()->json(['status' => false, 'msg' => 'Gagal memproses pembayaran borongan: '.$e->getMessage()], 422);
        }
    }

    /**
     * Kwitansi batch: satu PDF merangkum seluruh pembayaran dalam grup.
     * QR memakai verifikasi pembayaran pertama (tanda HMAC yang sama dengan
     * kwitansi tunggal) — halaman publik menampilkan rincian grup via batch_group_id.
     */
    public function batchPaymentReceipt(string $groupId)
    {
        $payments = BatchPaymentService::batchPayments($groupId);

        if ($payments->isEmpty()) {
            abort(404);
        }

        $receiptPayment = $payments->first();

        return PdfDocument::download(
            'finance.payment.batch-receipt',
            [
                'payments' => $payments,
                'groupId' => $groupId,
                'total' => (float) $payments->sum('amount'),
                'item' => $receiptPayment,
                'settings' => $this->companySettings(),
            ],
            'Kwitansi-Batch-'.substr($groupId, 0, 8)
        );
    }

    public function paymentReceipt($id)
    {
        $item = Payment::with(['invoice', 'rental.customer', 'rental.vehicle.model.brand'])->findOrFail($id);

        return PdfDocument::download(
            'finance.payment.receipt',
            ['item' => $item, 'settings' => $this->companySettings()],
            'Kwitansi-PAY-'.str_pad($item->payment_id, 5, '0', STR_PAD_LEFT)
        );
    }
}
