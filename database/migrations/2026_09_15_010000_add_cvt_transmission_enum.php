<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Audit M-03: form mengirim nilai 'cvt' yang tidak ada di enum -> strict MySQL menolak.
        Schema::table('m_vehicle_model', function (Blueprint $table) {
            $table->enum('transmission', ['Manual', 'Automatic', 'CVT'])->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('m_vehicle_model', function (Blueprint $table) {
            \DB::table('m_vehicle_model')->where('transmission', 'CVT')->update(['transmission' => 'Automatic']);
            $table->enum('transmission', ['Manual', 'Automatic'])->nullable()->change();
        });
    }
};
