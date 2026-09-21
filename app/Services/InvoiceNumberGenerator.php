<?php

namespace App\Services;

use App\Http\Controllers\Traits\NumberTrait;
use App\Models\Invoice;

/**
 * Penomoran invoice terisolasi dari controller (FIN-06) dengan memakai pola lock
 * yang sudah ada di NumberTrait: serialisasi FOR UPDATE sebelum membaca MAX agar
 * dua penerbitan paralel tidak membaca nomor yang sama. Unique constraint pada
 * invoice_number tetap menjadi jaring pengaman terakhir.
 */
class InvoiceNumberGenerator
{
    use NumberTrait;

    public function next(): string
    {
        return $this->gen_number(Invoice::class, 'invoice_number', 'INV-$/#####', now(), 'created_at', true);
    }
}
