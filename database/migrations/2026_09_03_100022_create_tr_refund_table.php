<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tr_refund', function (Blueprint $table) {
            $table->id('refund_id');
            $table->unsignedBigInteger('payment_id')->nullable();
            $table->unsignedBigInteger('rental_id');
            $table->dateTime('refund_date')->useCurrent();
            $table->decimal('amount', 12, 2);
            $table->enum('refund_type', ['deposit_return', 'overpayment', 'cancellation', 'damage_deposit']);
            $table->enum('status', ['pending', 'processed', 'failed'])->default('pending');
            $table->string('reference_number', 50)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->softDeletes();

            $table->foreign('payment_id')->references('payment_id')->on('tr_payment')->nullOnDelete();
            $table->foreign('rental_id')->references('rental_id')->on('tr_rental')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tr_refund');
    }
};