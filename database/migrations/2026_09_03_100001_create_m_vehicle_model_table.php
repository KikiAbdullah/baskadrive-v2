<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('m_vehicle_model', function (Blueprint $table) {
            $table->id('model_id');
            $table->unsignedBigInteger('brand_id');
            $table->string('model_name', 100)->comment('Nama model (Avanza, Xenia, dll.)');
            $table->string('category', 50)->nullable()->comment('Kategori: SUV, MPV, Sedan, Hatchback');
            $table->enum('fuel_type', ['Petrol', 'Diesel', 'Electric', 'Hybrid']);
            $table->enum('transmission', ['Manual', 'Automatic'])->nullable();
            $table->tinyInteger('seat_capacity')->nullable()->comment('Kapasitas penumpang');
            $table->decimal('base_price_per_day', 10, 2)->comment('Harga sewa per hari');
            $table->decimal('base_price_per_km', 10, 2)->nullable()->comment('Harga sewa per km (opsional)');
            $table->decimal('insurance_rate', 5, 2)->nullable()->comment('Rate asuransi per hari (%)');
            $table->decimal('deposit_amount', 10, 2)->nullable()->comment('Uang jaminan standar');
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('brand_id')->references('brand_id')->on('m_brand')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('m_vehicle_model');
    }
};
