<?php

namespace App\Http\Controllers\Rental;

use App\Http\Controllers\Controller;
use App\Models\Fine;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Rental;
use DB;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Spatie\LaravelPdf\Facades\Pdf;

class FinanceController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    // ============================================================
    // SATU HALAMAN: INVOICE / DENDA / PEMBAYARAN
    // ============================================================

    public function index(Request $request)
    {
        $tab = $request->get('tab', 'invoice');

        $tabs = [
            'invoice' => 'Invoice',
            'fine' => 'Denda',
            'payment' => 'Pembayaran',
        ];

        if (!array_key_exists($tab, $tabs)) {
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
        $query = Invoice::with(['rental.customer', 'rental.vehicle']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return datatables()->of($query)
            ->addColumn('invoice_number', fn($i) => $i->invoice_number ?? '-')
            ->addColumn('rental_code', fn($i) => $i->rental?->rental_code ?? '-')
            ->addColumn('customer', fn($i) => $i->rental?->customer?->full_name ?? '-')
            ->addColumn('issue_date', fn($i) => $i->issue_date?->format('d/m/Y') ?? '-')
            ->addColumn('due_date', fn($i) => $i->due_date?->format('d/m/Y') ?? '-')
            ->addColumn('total', fn($i) => 'Rp ' . number_format($i->total_amount ?? 0, 0, ',', '.'))
            ->addColumn('paid', fn($i) => 'Rp ' . number_format($i->paid_amount ?? 0, 0, ',', '.'))
            ->addColumn('remaining', fn($i) => 'Rp ' . number_format(($i->total_amount ?? 0) - ($i->paid_amount ?? 0), 0, ',', '.'))
            ->addColumn('status_badge', fn($i) => view('components.invoice-status-badge', ['status' => $i->status])->render())
            ->rawColumns(['status_badge'])
            ->toJson();
    }

    public function invoiceButtonOption(Request $request)
    {
        try {
            $item = Invoice::findOrFail($request->get('id'));

            return response()->json([
                'status' => true,
                'view' => view('finance.invoice.button_option')->with(['item' => $item])->render(),
            ]);
        } catch (Exception $e) {
            return response()->json(['status' => false, 'msg' => $e->getMessage()]);
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
            'subtitle' => 'Invoice ' . ($item->invoice_number ?? ''),
            'item' => $item,
        ]);
    }

    private function companySettings(): array
    {
        return \App\Support\AppSettings::all();
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

        return \App\Support\PdfDocument::download(
            'finance.invoice.print',
            ['item' => $item, 'settings' => $this->companySettings()],
            'Invoice-' . ($item->invoice_number ?? $item->invoice_id)
        );
    }

    public function invoiceSendEmail(Request $request, $id)
    {
        try {
            $item = Invoice::with(['rental.customer', 'rental.vehicle.model.brand', 'rental.driver', 'rental.details', 'rental.pickupLocation', 'rental.returnLocation', 'payments'])->findOrFail($id);

            $recipient = $item->rental?->customer?->email;
            if (! $recipient) {
                return response()->json(['status' => false, 'msg' => 'Pelanggan tidak memiliki alamat email. Perbarui data pelanggan terlebih dahulu.']);
            }

            $settings = $this->companySettings();

            // Generate PDF invoice ke folder temp untuk dilampirkan (dibersihkan oleh app:cleanup-temp)
            Storage::disk('local')->makeDirectory('pdf-tmp');
            $pdfPath = Storage::disk('local')->path('pdf-tmp/Invoice-' . ($item->invoice_number ?? $item->invoice_id) . '.pdf');

            Pdf::view('finance.invoice.print', ['item' => $item, 'settings' => $settings])
                ->format('a4')
                ->save($pdfPath);

            try {
                \Illuminate\Support\Facades\Mail::to($recipient)->send(new \App\Mail\InvoiceMail($item, $pdfPath, $settings));
            } finally {
                if (is_file($pdfPath)) {
                    @unlink($pdfPath);
                }
            }

            if ($item->status === 'draft') {
                $item->update(['status' => 'sent']);
            }

            return response()->json(['status' => true, 'msg' => 'Invoice PDF berhasil dikirim ke '.$recipient]);
        } catch (\Symfony\Component\Mailer\Exception\TransportException $e) {
            return response()->json(['status' => false, 'msg' => 'Gagal mengirim email (server surat tidak dapat dihubungi): '.$e->getMessage()]);
        } catch (Exception $e) {
            return response()->json(['status' => false, 'msg' => $e->getMessage()]);
        }
    }

    // ============================================================
    // FINE (DENDA)
    // ============================================================

    public function fineData(Request $request)
    {
        $query = Fine::with(['rental.customer', 'rental.vehicle', 'returnRecord']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return datatables()->of($query)
            ->addColumn('rental_code', fn($f) => $f->rental?->rental_code ?? '-')
            ->addColumn('customer', fn($f) => $f->rental?->customer?->full_name ?? '-')
            ->addColumn('vehicle', fn($f) => $f->rental?->vehicle?->license_plate ?? '-')
            ->addColumn('fine_type', fn($f) => ucfirst(str_replace('_', ' ', $f->fine_type ?? '-')))
            ->addColumn('amount', fn($f) => 'Rp ' . number_format($f->amount ?? 0, 0, ',', '.'))
            ->addColumn('issued_date', fn($f) => $f->issued_date?->format('d/m/Y') ?? '-')
            ->addColumn('status_badge', fn($f) => view('components.fine-status-badge', ['status' => $f->status])->render())
            ->rawColumns(['status_badge'])
            ->toJson();
    }

    public function fineButtonOption(Request $request)
    {
        try {
            $item = Fine::findOrFail($request->get('id'));

            return response()->json([
                'status' => true,
                'view' => view('finance.fine.button_option')->with(['item' => $item])->render(),
            ]);
        } catch (Exception $e) {
            return response()->json(['status' => false, 'msg' => $e->getMessage()]);
        }
    }

    public function finePay(Request $request, $id)
    {
        try {
            $fine = Fine::findOrFail($id);
            DB::beginTransaction();

            $fine->update([
                'status' => 'paid',
                'paid_date' => now(),
                'issued_by' => auth()->user()->employee_id ?? null,
            ]);

            Payment::create([
                'rental_id' => $fine->rental_id,
                'payment_date' => now(),
                'amount' => $fine->amount,
                'payment_method' => $request->payment_method ?? 'cash',
                'reference_number' => $request->reference_number ?? null,
                'status' => 'completed',
            ]);

            DB::commit();

            return response()->json(['status' => true, 'msg' => 'Denda berhasil dibayar.']);
        } catch (Exception $e) {
            DB::rollback();
            return response()->json(['status' => false, 'msg' => $e->getMessage()]);
        }
    }

    public function fineWaive(Request $request, $id)
    {
        try {
            $fine = Fine::findOrFail($id);
            $fine->update(['status' => 'waived']);

            return response()->json(['status' => true, 'msg' => 'Denda dibebaskan.']);
        } catch (Exception $e) {
            return response()->json(['status' => false, 'msg' => $e->getMessage()]);
        }
    }

    // ============================================================
    // PAYMENT (PEMBAYARAN)
    // ============================================================

    public function paymentData(Request $request)
    {
        $query = Payment::with(['invoice', 'rental.customer', 'rental.vehicle']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return datatables()->of($query)
            ->addColumn('receipt_number', fn($p) => 'PAY-' . str_pad($p->payment_id, 5, '0', STR_PAD_LEFT))
            ->addColumn('invoice_number', fn($p) => $p->invoice?->invoice_number ?? '-')
            ->addColumn('rental_code', fn($p) => $p->rental?->rental_code ?? '-')
            ->addColumn('customer', fn($p) => $p->rental?->customer?->full_name ?? '-')
            ->addColumn('payment_date', fn($p) => $p->payment_date?->format('d/m/Y') ?? '-')
            ->addColumn('amount', fn($p) => 'Rp ' . number_format($p->amount ?? 0, 0, ',', '.'))
            ->addColumn('method', fn($p) => ucfirst($p->payment_method ?? '-'))
            ->addColumn('status_badge', fn($p) => view('components.payment-status-badge', ['status' => $p->status])->render())
            ->rawColumns(['status_badge'])
            ->toJson();
    }

    public function paymentButtonOption(Request $request)
    {
        try {
            $item = Payment::findOrFail($request->get('id'));

            return response()->json([
                'status' => true,
                'view' => view('finance.payment.button_option')->with(['item' => $item])->render(),
            ]);
        } catch (Exception $e) {
            return response()->json(['status' => false, 'msg' => $e->getMessage()]);
        }
    }

    public function paymentReceipt($id)
    {
        $item = Payment::with(['invoice', 'rental.customer', 'rental.vehicle.model.brand'])->findOrFail($id);

        return \App\Support\PdfDocument::download(
            'finance.payment.receipt',
            ['item' => $item, 'settings' => $this->companySettings()],
            'Kwitansi-PAY-' . str_pad($item->payment_id, 5, '0', STR_PAD_LEFT)
        );
    }
}
