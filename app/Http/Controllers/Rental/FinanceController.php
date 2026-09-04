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

class FinanceController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    // ============================================================
    // INVOICE
    // ============================================================

    public function invoiceIndex()
    {
        return view('finance.invoice.index')->with([
            'title' => 'Invoice',
            'subtitle' => 'Daftar Invoice',
        ]);
    }

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
            ->addColumn('action', function ($i) {
                $html = '<div class="d-flex gap-1">';
                $html .= '<a href="' . route('finance.invoice.show', $i->invoice_id) . '" class="btn btn-sm btn-outline-primary" title="Detail"><i class="ri-eye-line"></i></a>';
                $html .= '<a href="' . route('finance.invoice.print', $i->invoice_id) . '" target="_blank" class="btn btn-sm btn-outline-secondary" title="Cetak"><i class="ri-printer-line"></i></a>';
                $html .= '<button type="button" class="btn btn-sm btn-outline-info btn-send" data-id="' . $i->invoice_id . '" title="Kirim Email"><i class="ri-mail-send-line"></i></button>';
                $html .= '</div>';
                return $html;
            })
            ->rawColumns(['status_badge', 'action'])
            ->toJson();
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

    public function invoicePrint($id)
    {
        $item = Invoice::with([
            'rental.customer',
            'rental.vehicle.model.brand',
            'rental.details',
            'rental.pickupLocation',
            'rental.returnLocation',
            'payments',
        ])->findOrFail($id);

        return view('finance.invoice.print')->with(['item' => $item]);
    }

    public function invoiceSendEmail(Request $request, $id)
    {
        try {
            $item = Invoice::findOrFail($id);
            if ($item->status === 'draft') {
                $item->update(['status' => 'sent']);
            }

            return response()->json(['status' => true, 'msg' => 'Invoice berhasil dikirim (simulasi).']);
        } catch (Exception $e) {
            return response()->json(['status' => false, 'msg' => $e->getMessage()]);
        }
    }

    // ============================================================
    // FINE (DENDA)
    // ============================================================

    public function fineIndex()
    {
        return view('finance.fine.index')->with([
            'title' => 'Denda',
            'subtitle' => 'Daftar Denda',
        ]);
    }

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
            ->addColumn('action', function ($f) {
                if ($f->status !== 'unpaid') {
                    return '<span class="text-muted">-</span>';
                }
                $html = '<div class="d-flex gap-1">';
                $html .= '<button type="button" class="btn btn-sm btn-success btn-pay" data-id="' . $f->fine_id . '"><i class="ri-check-line"></i> Bayar</button>';
                $html .= '<button type="button" class="btn btn-sm btn-outline-secondary btn-waive" data-id="' . $f->fine_id . '">Bebaskan</button>';
                $html .= '</div>';
                return $html;
            })
            ->rawColumns(['status_badge', 'action'])
            ->toJson();
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

    public function paymentIndex()
    {
        return view('finance.payment.index')->with([
            'title' => 'Pembayaran',
            'subtitle' => 'Daftar Pembayaran',
        ]);
    }

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
            ->addColumn('action', fn($p) => '<a href="' . route('finance.payment.receipt', $p->payment_id) . '" target="_blank" class="btn btn-sm btn-outline-primary"><i class="ri-receipt-line"></i> Kwitansi</a>')
            ->rawColumns(['status_badge', 'action'])
            ->toJson();
    }

    public function paymentReceipt($id)
    {
        $item = Payment::with(['invoice', 'rental.customer', 'rental.vehicle.model.brand'])->findOrFail($id);

        return view('finance.payment.receipt')->with(['item' => $item]);
    }
}
