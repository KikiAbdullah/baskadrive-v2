<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Audit M-04: form melabeli "No. SIM (opsional)" tetapi kolom NOT NULL + UNIQUE,
        // sehingga pelanggan kedua tanpa SIM menabrak "Duplicate entry ''".
        DB::table('m_customer')->where('driver_license_number', '')->update(['driver_license_number' => null]);

        Schema::table('m_customer', function (Blueprint $table) {
            // Index UNIQUE yang sudah ada tetap dipertahankan MySQL setelah MODIFY kolom
            $table->string('driver_license_number', 30)->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('m_customer')->whereNull('driver_license_number')->update(['driver_license_number' => '']);

        Schema::table('m_customer', function (Blueprint $table) {
            $table->string('driver_license_number', 30)->nullable(false)->change();
        });
    }
};
