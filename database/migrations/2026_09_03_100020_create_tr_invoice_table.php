<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tr_invoice', function (Blueprint $table) {
            $table->id('invoice_id');
            $table->unsignedBigInteger('rental_id');
            $table->string('invoice_number', 30)->unique();
            $table->dateTime('issue_date')->useCurrent();
            $table->dateTime('due_date');
            $table->decimal('sub_total', 12, 2);
            $table->decimal('tax', 10, 2)->default(0);
            $table->decimal('discount', 10, 2)->default(0);
            $table->decimal('total_amount', 12, 2);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->enum('status', ['draft', 'sent', 'partially_paid', 'paid', 'overdue', 'cancelled'])
                ->default('draft');
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->foreign('rental_id')->references('rental_id')->on('tr_rental')->cascadeOnDelete();

            $table->index('rental_id', 'idx_invoice_rental');
            $table->index('status', 'idx_invoice_status');
            $table->index('invoice_number', 'idx_invoice_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tr_invoice');
    }
};