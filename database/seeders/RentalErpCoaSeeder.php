<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RentalErpCoaSeeder extends Seeder
{
    /**
     * Chart of Account (COA) — Standar Akuntansi Indonesia.
     * Struktur: [account_code, account_name, account_type, parent_code]
     */
    private array $coa = [
        // ASET
        ['1-1000', 'Kas & Bank', 'asset', null],
        ['1-1100', 'Kas Besar', 'asset', '1-1000'],
        ['1-1200', 'Rekening Bank', 'asset', '1-1000'],
        ['1-2000', 'Piutang', 'asset', null],
        ['1-2100', 'Piutang Sewa', 'asset', '1-2000'],
        ['1-3000', 'Inventaris & Armada', 'asset', null],
        ['1-3100', 'Kendaraan', 'asset', '1-3000'],
        ['1-3200', 'Akumulasi Depresiasi', 'asset', '1-3000'],
        ['1-4000', 'Aset Lainnya', 'asset', null],

        // KEWAJIBAN
        ['2-1000', 'Utang Usaha', 'liability', null],
        ['2-2000', 'Utang Jangka Panjang', 'liability', null],
        ['2-3000', 'Pendapatan Diterima Dimuka', 'liability', null],
        ['2-4000', 'Kewajiban Lainnya', 'liability', null],

        // EKUITAS
        ['3-1000', 'Modal', 'equity', null],
        ['3-2000', 'Laba Ditahan', 'equity', null],

        // PENDAPATAN
        ['4-1000', 'Pendapatan Sewa', 'income', null],
        ['4-1100', 'Pendapatan Sewa Dasar', 'income', '4-1000'],
        ['4-1200', 'Pendapatan Sewa Tambahan', 'income', '4-1000'],
        ['4-2000', 'Pendapatan Denda', 'income', null],
        ['4-3000', 'Pendapatan Klaim Asuransi', 'income', null],

        // BEBAN
        ['5-1000', 'Beban Operasional', 'expense', null],
        ['5-1100', 'Beban Gaji', 'expense', '5-1000'],
        ['5-1200', 'Beban BBM & Tol', 'expense', '5-1000'],
        ['5-2000', 'Beban Maintenance & Repair', 'expense', null],
        ['5-2100', 'Beban Servis', 'expense', '5-2000'],
        ['5-2200', 'Beban Suku Cadang', 'expense', '5-2000'],
        ['5-3000', 'Beban Depresiasi', 'expense', null],
        ['5-4000', 'Beban Asuransi', 'expense', null],
    ];

    public function run(): void
    {
        $ids = [];
        $created = 0;

        foreach ($this->coa as [$code, $name, $type, $parent]) {
            // Idempoten: lewati akun yang sudah ada (misal dibuat oleh migration) agar seed
            // tidak menabrak unique account_code saat dijalankan setelah migrasi (audit M-04).
            $existing = DB::table('m_coa')->where('account_code', $code)->first();
            if ($existing) {
                $ids[$code] = $existing->account_id;

                continue;
            }

            $id = DB::table('m_coa')->insertGetId([
                'account_code' => $code,
                'account_name' => $name,
                'account_type' => $type,
                'parent_id' => $parent ? ($ids[$parent] ?? null) : null,
                'is_active' => true,
            ]);

            $ids[$code] = $id;
            $created++;
        }

        $this->command->info('RentalErpCoaSeeder selesai: '.$created.' akun baru dari '.count($this->coa).' total.');
    }
}