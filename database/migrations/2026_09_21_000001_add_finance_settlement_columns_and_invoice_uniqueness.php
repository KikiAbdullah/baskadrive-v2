<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Remediasi FIN-02/FIN-03/FIN-06 (audit keuangan 15092026):
 * - tr_payment.allocation        : pembeda pembayaran pokok sewa vs denda agar
 *                                  settlement & laporan tidak mencampur keduanya.
 * - tr_fine.paid_by/waived_by/at : identitas petugas pemisah dari issued_by (penerbit).
 * - tr_invoice rental_id unik    : kardinalitas satu invoice aktif per rental dijaga
 *                                  database (bukan hanya pengecekan aplikasi).
 * Index allocation dibuat non-unique: deployment lama dapat punya pembayaran denda
 * historis; unique hanya dijaga aplikasi (satu settlement per denda).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tr_payment', function (Blueprint $table) {
            if (! Schema::hasColumn('tr_payment', 'allocation')) {
                $table->string('allocation', 20)->default('rental')->after('status');
                $table->index('allocation', 'idx_payment_allocation');
            }
        });

        Schema::table('tr_fine', function (Blueprint $table) {
            if (! Schema::hasColumn('tr_fine', 'paid_by')) {
                $table->unsignedBigInteger('paid_by')->nullable()->after('paid_date');
            }
            if (! Schema::hasColumn('tr_fine', 'waived_by')) {
                $table->unsignedBigInteger('waived_by')->nullable()->after('paid_by');
            }
            if (! Schema::hasColumn('tr_fine', 'waived_at')) {
                $table->dateTime('waived_at')->nullable()->after('waived_by');
            }
        });

        if (Schema::hasTable('tr_invoice') && ! $this->invoiceUniqueIndexExists()) {
            Schema::table('tr_invoice', function (Blueprint $table) {
                $table->unique('rental_id', 'uq_invoice_rental_active');
            });
        }
    }

    public function down(): void
    {
        Schema::table('tr_payment', function (Blueprint $table) {
            if (Schema::hasColumn('tr_payment', 'allocation')) {
                $table->dropIndex('idx_payment_allocation');
                $table->dropColumn('allocation');
            }
        });

        Schema::table('tr_fine', function (Blueprint $table) {
            foreach (['paid_by', 'waived_by', 'waived_at'] as $col) {
                if (Schema::hasColumn('tr_fine', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        if (Schema::hasTable('tr_invoice') && $this->invoiceUniqueIndexExists()) {
            Schema::table('tr_invoice', function (Blueprint $table) {
                $table->dropUnique('uq_invoice_rental_active');
            });
        }
    }

    private function invoiceUniqueIndexExists(): bool
    {
        try {
            $indexes = Schema::getIndexes('tr_invoice');

            return in_array('uq_invoice_rental_active', array_column($indexes, 'name'), true);
        } catch (Throwable $e) {
            return false;
        }
    }
};
