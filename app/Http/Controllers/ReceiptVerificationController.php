<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Support\QrCode;
use Illuminate\Http\Request;

/**
 * Verifikasi publik keaslian kwitansi pembayaran via QR Code (audit 3).
 * Tidak menampilkan data sensitif: hanya status, nomor, nominal, dan tanggal.
 */
class ReceiptVerificationController extends Controller
{
    public function verify(Request $request, $payment)
    {
        $record = Payment::with(['rental.customer', 'invoice'])->find($payment);

        $valid = $request->filled('t') && hash_equals(
            QrCode::receiptSignature((int) $payment),
            (string) $request->input('t')
        );

        if (! $record || ! $valid) {
            return response()->view('verify.receipt', ['record' => null, 'id' => $payment], 404);
        }

        return view('verify.receipt')->with([
            'record' => $record,
            'id' => $payment,
        ]);
    }
}
