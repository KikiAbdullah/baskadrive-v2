<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('m_coa', function (Blueprint $table) {
            $table->id('account_id');
            $table->string('account_code', 20)->unique();
            $table->string('account_name', 100);
            $table->enum('account_type', ['asset', 'liability', 'equity', 'income', 'expense']);
            $table->unsignedBigInteger('parent_id')->nullable()->comment('Self-reference untuk hierarki');
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('parent_id')->references('account_id')->on('m_coa')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('m_coa');
    }
};
