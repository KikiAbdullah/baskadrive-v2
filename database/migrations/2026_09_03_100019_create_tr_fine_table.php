<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tr_fine', function (Blueprint $table) {
            $table->id('fine_id');
            $table->unsignedBigInteger('rental_id');
            $table->unsignedBigInteger('return_id')->nullable();
            $table->enum('fine_type', ['late_return', 'damage', 'cleaning', 'fuel', 'lost_item', 'other']);
            $table->text('description')->nullable();
            $table->decimal('amount', 12, 2);
            $table->enum('status', ['unpaid', 'paid', 'waived'])->default('unpaid');
            $table->dateTime('issued_date')->useCurrent();
            $table->dateTime('paid_date')->nullable();
            $table->unsignedBigInteger('issued_by')->nullable();
            $table->text('notes')->nullable();

            $table->foreign('rental_id')->references('rental_id')->on('tr_rental')->cascadeOnDelete();
            $table->foreign('return_id')->references('return_id')->on('tr_return')->nullOnDelete();
            $table->foreign('issued_by')->references('employee_id')->on('m_employee')->nullOnDelete();

            $table->index('rental_id', 'idx_fine_rental');
            $table->index('status', 'idx_fine_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tr_fine');
    }
};