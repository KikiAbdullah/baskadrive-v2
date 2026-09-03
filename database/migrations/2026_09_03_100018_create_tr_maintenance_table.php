<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tr_maintenance', function (Blueprint $table) {
            $table->id('maintenance_id');
            $table->unsignedBigInteger('vehicle_id');
            $table->unsignedBigInteger('workshop_id');
            $table->unsignedBigInteger('maintenance_type_id');
            $table->dateTime('scheduled_date');
            $table->dateTime('actual_date')->nullable();
            $table->integer('current_mileage');
            $table->decimal('cost', 12, 2)->default(0);
            $table->text('description')->nullable();
            $table->enum('status', ['scheduled', 'in_progress', 'completed', 'cancelled', 'overdue'])
                ->default('scheduled');
            $table->integer('next_maintenance_km')->nullable()->comment('Rekomendasi km berikutnya');
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->foreign('vehicle_id')->references('vehicle_id')->on('m_vehicle')->restrictOnDelete();
            $table->foreign('workshop_id')->references('workshop_id')->on('m_workshop')->restrictOnDelete();
            $table->foreign('maintenance_type_id')->references('type_id')->on('m_maintenance_type')->restrictOnDelete();

            $table->index('vehicle_id', 'idx_maintenance_vehicle');
            $table->index('status', 'idx_maintenance_status');
            $table->index('scheduled_date', 'idx_maintenance_scheduled');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tr_maintenance');
    }
};