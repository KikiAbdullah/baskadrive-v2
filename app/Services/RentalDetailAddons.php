<?php

namespace App\Services;

use App\Models\RentalDetail;
use Illuminate\Support\Facades\DB;

/**
 * Helper agregasi add-on sewa untuk perhitungan subtotal invoice (FIN-06/FIN-07).
 * Dipisah dari controller agar service settlement tidak bergantung pada controller.
 */
class RentalDetailAddons
{
    public static function sumFor(int $rentalId): float
    {
        return (float) DB::table((new RentalDetail)->getTable())
            ->where('rental_id', $rentalId)
            ->sum(DB::raw('COALESCE(total_price, quantity * unit_price, 0)'));
    }
}
