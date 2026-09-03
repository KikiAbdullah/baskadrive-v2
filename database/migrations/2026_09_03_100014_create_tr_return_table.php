<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tr_return', function (Blueprint $table) {
            $table->id('return_id');
            $table->unsignedBigInteger('rental_id')->unique()->comment('1 rental hanya 1 pengembalian');
            $table->dateTime('return_date');
            $table->integer('return_mileage')->comment('Kilometer saat pengembalian');
            $table->enum('fuel_level', ['full', 'three_quarter', 'half', 'quarter', 'empty'])->nullable();
            $table->enum('vehicle_condition', ['excellent', 'good', 'fair', 'damaged'])->nullable();
            $table->text('damage_description')->nullable();
            $table->decimal('repair_cost_estimate', 12, 2)->default(0);
            $table->decimal('extra_charge', 10, 2)->default(0)->comment('Biaya tambahan dari inspeksi');
            $table->decimal('deposit_refund', 10, 2)->default(0);
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('rental_id')->references('rental_id')->on('tr_rental')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tr_return');
    }
};