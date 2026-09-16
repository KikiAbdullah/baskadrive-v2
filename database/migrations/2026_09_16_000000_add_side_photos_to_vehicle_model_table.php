<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('m_vehicle_model', function (Blueprint $table) {
            $table->string('photo_depan')->nullable()->after('photo');
            $table->string('photo_belakang')->nullable()->after('photo_depan');
            $table->string('photo_kiri')->nullable()->after('photo_belakang');
            $table->string('photo_kanan')->nullable()->after('photo_kiri');
        });
    }

    public function down(): void
    {
        Schema::table('m_vehicle_model', function (Blueprint $table) {
            $table->dropColumn(['photo_depan', 'photo_belakang', 'photo_kiri', 'photo_kanan']);
        });
    }
};
