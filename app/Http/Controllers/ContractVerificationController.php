<?php

namespace App\Http\Controllers;

use App\Models\Rental;
use App\Support\QrCode;
use Illuminate\Http\Request;

/**
 * Verifikasi publik keaslian kontrak sewa via QR Code (butir 3 audit_12092026).
 * Pola sama dengan verifikasi kwitansi: tidak menampilkan data sensitif —
 * hanya status, nomor kontrak, penyewa (nama, tanpa kontak), periode, dan unit.
 */
class ContractVerificationController extends Controller
{
    public function verify(Request $request, $rental)
    {
        $record = Rental::with(['customer', 'vehicle.model.brand'])->find($rental);

        $valid = $request->filled('t') && hash_equals(
            QrCode::contractSignature((int) $rental),
            (string) $request->input('t')
        );

        if (! $record || ! $valid) {
            return response()->view('verify.contract', ['record' => null, 'id' => $rental], 404);
        }

        return view('verify.contract')->with([
            'record' => $record,
            'id' => $rental,
        ]);
    }
}
