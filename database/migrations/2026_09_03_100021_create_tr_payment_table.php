<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tr_payment', function (Blueprint $table) {
            $table->id('payment_id');
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->unsignedBigInteger('rental_id');
            $table->dateTime('payment_date')->useCurrent();
            $table->decimal('amount', 12, 2);
            $table->enum('payment_method', ['cash', 'bank_transfer', 'credit_card', 'debit_card', 'e_wallet', 'other']);
            $table->string('reference_number', 50)->nullable();
            $table->enum('status', ['pending', 'completed', 'failed', 'refunded'])->default('pending');
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('invoice_id')->references('invoice_id')->on('tr_invoice')->nullOnDelete();
            $table->foreign('rental_id')->references('rental_id')->on('tr_rental')->cascadeOnDelete();

            $table->index('invoice_id', 'idx_payment_invoice');
            $table->index('rental_id', 'idx_payment_rental');
            $table->index('status', 'idx_payment_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tr_payment');
    }
};