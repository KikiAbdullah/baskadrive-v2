<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Empat butir tersisa audit_12092026.md:
 *
 * 1. Komisi sopir (2.2.5) — m_driver.commission_percent (default 0-100, 10 = 10%);
 *    akrual jurnal dijalankan service, bukan skema.
 * 3. Batch payment multi-invoice (2.5) — tr_payment.batch_group_id: identitas
 *    pembayaran borongan; kwitansi batch diproduksi dari grup ini. Dibuat nullable
 *    (pembayaran tunggal tidak berganti perilaku) dengan index non-unique — satu
 *    pembayaran borongan boleh mencakup banyak baris (1 header + N invoice).
 * 2. Payment gateway (2.5) — tanpa perubahan skema pada fase ini: integrasi vendor
 *    membutuhkan keputusan bisnis/kredensial; infrastruktur channel (enum payment
 *    method + settlement) sudah disiapkan di kode.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('m_driver', function (Blueprint $table) {
            if (! Schema::hasColumn('m_driver', 'commission_percent')) {
                $table->decimal('commission_percent', 5, 2)->default(0)->after('is_active');
            }
        });

        Schema::table('tr_payment', function (Blueprint $table) {
            if (! Schema::hasColumn('tr_payment', 'payment_number')) {
                // Nomor kwitansi (butir 2.5): pembayaran borongan memakai satu nomor
                // kwitansi BATCH-XXXXXXXX untuk seluruh grup; pembayaran tunggal kosong.
                $table->string('payment_number', 30)->nullable()->after('payment_id');
            }

            if (! Schema::hasColumn('tr_payment', 'batch_group_id')) {
                $table->string('batch_group_id', 36)->nullable()->after('allocation');
                $table->index('batch_group_id', 'idx_payment_batch_group');
            }
        });
    }

    public function down(): void
    {
        Schema::table('m_driver', function (Blueprint $table) {
            if (Schema::hasColumn('m_driver', 'commission_percent')) {
                $table->dropColumn('commission_percent');
            }
        });

        Schema::table('tr_payment', function (Blueprint $table) {
            if (Schema::hasColumn('tr_payment', 'payment_number')) {
                $table->dropColumn('payment_number');
            }

            if (Schema::hasColumn('tr_payment', 'batch_group_id')) {
                $table->dropIndex('idx_payment_batch_group');
                $table->dropColumn('batch_group_id');
            }
        });
    }
};
