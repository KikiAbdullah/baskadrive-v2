<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tr_journal_detail', function (Blueprint $table) {
            $table->id('detail_id');
            $table->unsignedBigInteger('journal_id');
            $table->unsignedBigInteger('account_id');
            $table->decimal('debit', 15, 2)->default(0);
            $table->decimal('credit', 15, 2)->default(0);
            $table->text('description')->nullable();

            $table->foreign('journal_id')->references('journal_id')->on('tr_journal')->cascadeOnDelete();
            $table->foreign('account_id')->references('account_id')->on('m_coa')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tr_journal_detail');
    }
};