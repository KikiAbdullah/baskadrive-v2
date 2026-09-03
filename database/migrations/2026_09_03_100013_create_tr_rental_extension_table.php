<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tr_rental_extension', function (Blueprint $table) {
            $table->id('extension_id');
            $table->unsignedBigInteger('rental_id');
            $table->dateTime('old_end_date');
            $table->dateTime('new_end_date');
            $table->integer('extended_days');
            $table->decimal('additional_base_price', 12, 2)->default(0);
            $table->decimal('additional_tax', 10, 2)->default(0);
            $table->decimal('additional_total', 12, 2)->default(0);
            $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled'])->default('pending');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->dateTime('approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('rental_id')->references('rental_id')->on('tr_rental')->cascadeOnDelete();
            $table->foreign('approved_by')->references('employee_id')->on('m_employee')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tr_rental_extension');
    }
};