<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tr_rental', function (Blueprint $table) {
            // Null = ikut pengaturan global (settings.json).
            // 0 = sewa ini tanpa PPN.
            // >0 = PPN kustom per sewa.
            $table->decimal('tax_percent', 5, 2)->nullable()->after('tax_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tr_rental', function (Blueprint $table) {
            $table->dropColumn('tax_percent');
        });
    }
};
