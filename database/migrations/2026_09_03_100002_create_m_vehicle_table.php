<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('m_vehicle', function (Blueprint $table) {
            $table->id('vehicle_id');
            $table->string('license_plate', 15)->unique()->comment('Plat nomor');
            $table->string('vin', 17)->unique()->nullable()->comment('Nomor rangka (VIN)');
            $table->unsignedBigInteger('model_id');
            $table->string('color', 30)->nullable();
            $table->integer('year')->nullable()->comment('Tahun pembuatan');
            $table->integer('mileage')->default(0)->comment('Kilometer terakhir tercatat');
            $table->enum('status', ['available', 'rented', 'maintenance', 'reserved', 'retired'])
                ->default('available')->comment('Status kendaraan');
            $table->date('purchase_date')->nullable();
            $table->decimal('purchase_price', 15, 2)->nullable();
            $table->decimal('current_value', 15, 2)->nullable()->comment('Nilai saat ini (depresiasi)');
            $table->string('engine_number', 50)->nullable();
            $table->string('photo_url', 255)->nullable();
            $table->text('notes')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->foreign('model_id')->references('model_id')->on('m_vehicle_model')->restrictOnDelete();

            $table->index('model_id', 'idx_vehicle_model');
            $table->index('status', 'idx_vehicle_status');
            $table->index('license_plate', 'idx_vehicle_plate');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('m_vehicle');
    }
};
