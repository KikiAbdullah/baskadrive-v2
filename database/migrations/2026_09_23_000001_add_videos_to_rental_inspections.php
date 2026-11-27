<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Inspeksi video (praktik lapangan): rekaman seluruh sisi + interior unit
 * saat pengambilan (handover_out) dan pengembalian (handover_in).
 * Path file disimpan di disk `public`, folder `inspection-videos/`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rental_inspections', function (Blueprint $table) {
            $table->json('videos')->nullable()->after('notes')
                ->comment('Array path video inspeksi di disk public (inspection-videos/)');
            $table->unsignedInteger('video_total_seconds')->nullable()->after('videos')
                ->comment('Total durasi semua video (detik) — audit ringkas');
        });
    }

    public function down(): void
    {
        Schema::table('rental_inspections', function (Blueprint $table) {
            $table->dropColumn(['videos', 'video_total_seconds']);
        });
    }
};
