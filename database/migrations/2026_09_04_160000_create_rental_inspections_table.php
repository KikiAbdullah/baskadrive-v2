<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rental_inspections', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('rental_id');
            $table->unsignedBigInteger('vehicle_id');
            $table->enum('inspection_type', ['handover_out', 'handover_in'])->default('handover_out');
            $table->integer('odometer')->nullable();
            $table->enum('fuel_level', ['full', 'three_quarter', 'half', 'quarter', 'empty'])->nullable();
            $table->json('body_damage_points')->nullable()->comment('Array titik kerusakan bodi {x,y,note}');
            $table->json('checklist')->nullable()->comment('Kelengkapan: STNK, dongkrak, ban serep, segitiga, P3K, APAR, dll.');
            $table->text('exterior_notes')->nullable();
            $table->text('interior_notes')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('rental_id')->references('rental_id')->on('tr_rental')->onDelete('cascade');
            $table->foreign('vehicle_id')->references('vehicle_id')->on('m_vehicle')->onDelete('cascade');
            $table->index(['rental_id', 'inspection_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_inspections');
    }
};
