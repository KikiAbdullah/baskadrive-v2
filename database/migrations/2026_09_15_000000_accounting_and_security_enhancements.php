<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Audit 2.6: tipe 'manual' dipakai JurnalManualStore tapi tidak ada di enum awal,
        // ditambahkan 'insurance' untuk jurnal pencairan klaim asuransi.
        Schema::table('tr_journal', function (Blueprint $table) {
            $table->enum('journal_type', ['rental', 'payment', 'refund', 'maintenance', 'fine', 'adjustment', 'manual', 'insurance'])->change();
        });

        // Akun pendapatan klaim asuransi untuk jurnal pencairan (audit 2.4)
        if (! DB::table('m_coa')->where('account_code', '4-3000')->exists()) {
            DB::table('m_coa')->insert([
                'account_code' => '4-3000',
                'account_name' => 'Pendapatan Klaim Asuransi',
                'account_type' => 'income',
                'parent_id' => null,
                'is_active' => true,
            ]);
        }

        // Audit 2.9: masa kedaluwarsa token 2FA
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('token_2fa_expires_at')->nullable()->after('token_last_request');
        });

        // Audit 2.4: pencatatan tanggal pencairan klaim
        Schema::table('tr_insurance_claim', function (Blueprint $table) {
            $table->date('paid_date')->nullable()->after('approved_date');
        });

        // Audit 2.1: koordinat GPS per unit untuk peta armada
        Schema::table('m_vehicle', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable()->after('notes');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
        });
    }

    public function down(): void
    {
        Schema::table('m_vehicle', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude']);
        });

        Schema::table('tr_insurance_claim', function (Blueprint $table) {
            $table->dropColumn('paid_date');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('token_2fa_expires_at');
        });

        DB::table('m_coa')->where('account_code', '4-3000')->delete();

        Schema::table('tr_journal', function (Blueprint $table) {
            $table->enum('journal_type', ['rental', 'payment', 'refund', 'maintenance', 'fine', 'adjustment'])->change();
        });
    }
};
