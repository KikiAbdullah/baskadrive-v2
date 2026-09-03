<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tr_damage_report', function (Blueprint $table) {
            $table->id('damage_id');
            $table->unsignedBigInteger('rental_id');
            $table->unsignedBigInteger('vehicle_id');
            $table->unsignedBigInteger('return_id')->nullable();
            $table->dateTime('reported_date')->useCurrent();
            $table->enum('damage_type', ['exterior', 'interior', 'mechanical', 'electrical', 'glass', 'tire', 'other']);
            $table->enum('severity', ['minor', 'moderate', 'severe', 'total_loss']);
            $table->string('location', 100)->nullable()->comment('Lokasi spesifik kerusakan');
            $table->text('description')->nullable();
            $table->decimal('repair_cost_estimate', 12, 2)->default(0);
            $table->decimal('actual_repair_cost', 12, 2)->default(0);
            $table->enum('status', ['reported', 'assessment', 'repair_in_progress', 'repaired', 'claimed_insurance', 'written_off'])
                ->default('reported');
            $table->unsignedBigInteger('inspected_by')->nullable();
            $table->dateTime('inspected_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->foreign('rental_id')->references('rental_id')->on('tr_rental')->cascadeOnDelete();
            $table->foreign('vehicle_id')->references('vehicle_id')->on('m_vehicle')->restrictOnDelete();
            $table->foreign('return_id')->references('return_id')->on('tr_return')->nullOnDelete();
            $table->foreign('inspected_by')->references('employee_id')->on('m_employee')->nullOnDelete();

            $table->index('rental_id', 'idx_damage_rental');
            $table->index('vehicle_id', 'idx_damage_vehicle');
            $table->index('status', 'idx_damage_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tr_damage_report');
    }
};