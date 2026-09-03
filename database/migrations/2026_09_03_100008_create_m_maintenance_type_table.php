<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('m_maintenance_type', function (Blueprint $table) {
            $table->id('type_id');
            $table->string('type_name', 50)->unique();
            $table->integer('interval_km')->nullable()->comment('Interval kilometer (0 = tidak berdasarkan km)');
            $table->integer('interval_months')->nullable()->comment('Interval bulan (0 = tidak berdasarkan bulan)');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('m_maintenance_type');
    }
};
