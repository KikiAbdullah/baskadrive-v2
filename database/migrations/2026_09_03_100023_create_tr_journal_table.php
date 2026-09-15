<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tr_journal', function (Blueprint $table) {
            $table->id('journal_id');
            $table->dateTime('transaction_date')->useCurrent();
            $table->string('reference_number', 50)->unique()->nullable();
            $table->text('description')->nullable();
            $table->enum('journal_type', ['rental', 'payment', 'refund', 'maintenance', 'fine', 'adjustment', 'manual', 'insurance']);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('created_by')->references('employee_id')->on('m_employee')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tr_journal');
    }
};