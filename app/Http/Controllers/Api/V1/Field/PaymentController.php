<?php

namespace App\Http\Controllers\Api\V1\Field;

use App\Http\Controllers\Api\ApiController;
use App\Http\Controllers\Api\V1\Field\Concerns\InteractsWithFieldApi;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Rental;
use App\Services\RentalSettlementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * F-07 — Pembayaran lapangan (konsep B.5).
 *
 * Pembayaran memakai `RentalSettlementService::recordPayment` — service yang
 * sama dengan web — sehingga rekonsiliasi invoice/rental + jurnal Dr Kas/Bank
 * identik. Batas wewenang operator (operator_payment_limit) berlaku: melebihi
 * → tetap dicatat sebagai `pending` untuk approval kantor.
 */
class PaymentController extends ApiController
{
    use InteractsWithFieldApi;

    /** POST /payments — multipart (konsep B.5). */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'rental_id' => 'required|exists:tr_rental,rental_id',
            'invoice_id' => 'nullable|exists:tr_invoice,invoice_id',
            'amount' => 'required|numeric|min:0.01|max:9999999999.99',
            // Kontrak mobile `cash|transfer|qris` → alias ke enum DB web.
            'method' => 'required|in:cash,transfer,qris',
            'reference_no' => 'nullable|string|max:50',
            'occurred_at' => 'nullable|date',
            'lat' => 'nullable|numeric|between:-90,90',
            'lng' => 'nullable|numeric|between:-180,180',
            'proof_photo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:10240',
        ]);

        return $this->idempotent($request, 'payments', function () use ($request) {
            $rental = Rental::findOrFail($request->integer('rental_id'));
            $methodMap = ['transfer' => 'bank_transfer', 'qris' => 'e_wallet'];
            $method = $methodMap[$request->input('method')] ?? $request->input('method');
            $amount = (float) $request->input('amount');

            $limit = (float) config('field.operator_payment_limit', 5000000);
            $needsApproval = $limit > 0 && $amount > $limit;

            if ($needsApproval) {
                // Melebihi batas wewenang → pending approval (konsep B.5).
                $payment = Payment::create([
                    'invoice_id' => $request->filled('invoice_id')
                        ? $request->integer('invoice_id')
                        : $rental->invoices()->whereNotIn('status', ['paid', 'cancelled'])->first()?->invoice_id,
                    'rental_id' => $rental->rental_id,
                    'payment_date' => $request->filled('occurred_at')
                        ? \Carbon\Carbon::parse($request->input('occurred_at'))
                        : now(),
                    'amount' => $amount,
                    'payment_method' => $method,
                    'reference_number' => $request->input('reference_no'),
                    'status' => 'pending',
                    'allocation' => Payment::ALLOCATION_RENTAL,
                    'notes' => 'Menunggu persetujuan — melebihi batas wewenang operator (Rp '.number_format($limit, 0, ',', '.').')',
                ]);

                $this->audit($request, 'create', 'field-payment', 'Pembayaran mobile '.$rental->rental_code.' PENDING APPROVAL Rp '.number_format($amount, 0, ',', '.'));

                return [
                    'payment_id' => $payment->payment_id,
                    'status' => 'pending_approval',
                    'amount' => $amount,
                    'operator_payment_limit' => $limit,
                ];
            }

            // Dalam batas wewenang → jalur normal, service web yang sama.
            $result = app(RentalSettlementService::class)->recordPayment($rental, [
                'amount' => $amount,
                'payment_method' => $method,
                'reference_number' => $request->input('reference_no'),
                'notes' => 'Diterima operator lapangan via mobile.',
            ]);

            if ($request->hasFile('proof_photo')) {
                $proofPath = $request->file('proof_photo')->store('payment-proofs', 'public');
                $result['payment']->update(['notes' => 'Bukti: '.Storage::disk('public')->url($proofPath)]);
            }

            $this->audit($request, 'create', 'field-payment', 'Pembayaran mobile '.$rental->rental_code.' Rp '.number_format($amount, 0, ',', '.').' ('.$method.')');

            $invoice = $result['invoice'];

            return [
                'payment_id' => $result['payment']->payment_id,
                'payment_no' => 'PAY-'.str_pad((string) $result['payment']->payment_id, 5, '0', STR_PAD_LEFT),
                'status' => 'completed',
                'amount' => $amount,
                'rental_payment_status' => $result['rental']->payment_status,
                'invoice_status' => $invoice?->status,
                'receipt_url' => $invoice ? route('rental.detail.invoice.print', ['rental' => $rental->rental_id]) : null,
            ];
        });
    }

    /** GET /invoices/{invoice}/receipt-url — URL kwitansi PDF (konsep B.5). */
    public function receiptUrl(Request $request, Invoice $invoice): JsonResponse
    {
        // Kwitansi invoice dirender per sewa (rental.invoice.print); invoice
        // tanpa sewa (kasus lama) jatuh ke cetak finance.
        $url = $invoice->rental_id
            ? route('rental.detail.invoice.print', ['rental' => $invoice->rental_id])
            : route('finance.invoice.print', ['id' => $invoice->invoice_id]);

        return response()->json(responseSuccess([
            'invoice_id' => $invoice->invoice_id,
            'invoice_number' => $invoice->invoice_number,
            'receipt_url' => url($url),
        ], 'URL kwitansi'));
    }
}
