<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tr_rental', function (Blueprint $table) {
            $table->id('rental_id');
            $table->string('rental_code', 20)->unique()->comment('Kode unik (RNT-2026-0001)');
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('vehicle_id');
            $table->unsignedBigInteger('employee_id')->nullable()->comment('Karyawan yang memproses');
            $table->unsignedBigInteger('pickup_location_id');
            $table->unsignedBigInteger('return_location_id');
            $table->unsignedBigInteger('promo_id')->nullable();

            $table->dateTime('rental_start_date');
            $table->dateTime('rental_end_date');
            $table->dateTime('actual_return_date')->nullable();
            $table->integer('rental_days')->nullable()->comment('Jumlah hari sewa (auto calculated)');

            $table->boolean('is_with_driver')->default(false);
            $table->unsignedBigInteger('driver_id')->nullable();

            $table->decimal('base_rate_per_day', 10, 2);
            $table->decimal('total_base_price', 12, 2)->nullable();
            $table->decimal('insurance_fee', 10, 2)->default(0);
            $table->decimal('driver_fee', 10, 2)->default(0);
            $table->decimal('young_driver_fee', 10, 2)->default(0);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->decimal('tax_percent', 5, 2)->nullable()->comment('PPN kustom per sewa; null = ikut pengaturan global');
            $table->decimal('deposit_amount', 10, 2)->nullable();
            $table->decimal('total_amount', 12, 2)->nullable();

            $table->enum('status', ['reserved', 'ongoing', 'completed', 'cancelled', 'overdue'])
                ->default('reserved')->comment('Status transaksi');
            $table->enum('payment_status', ['unpaid', 'partial', 'paid', 'refunded'])->default('unpaid');
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->softDeletes();

            $table->foreign('customer_id')->references('customer_id')->on('m_customer')->restrictOnDelete();
            $table->foreign('vehicle_id')->references('vehicle_id')->on('m_vehicle')->restrictOnDelete();
            $table->foreign('employee_id')->references('employee_id')->on('m_employee')->nullOnDelete();
            $table->foreign('pickup_location_id')->references('location_id')->on('m_location')->restrictOnDelete();
            $table->foreign('return_location_id')->references('location_id')->on('m_location')->restrictOnDelete();
            $table->foreign('promo_id')->references('promo_id')->on('m_promo')->nullOnDelete();
            $table->foreign('driver_id')->references('driver_id')->on('m_driver')->nullOnDelete();

            $table->index('customer_id', 'idx_rental_customer');
            $table->index('vehicle_id', 'idx_rental_vehicle');
            $table->index(['rental_start_date', 'rental_end_date'], 'idx_rental_dates');
            $table->index('status', 'idx_rental_status');
            $table->index('payment_status', 'idx_rental_payment_status');
            $table->index('rental_code', 'idx_rental_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tr_rental');
    }
};