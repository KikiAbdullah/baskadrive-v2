<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tr_insurance_claim', function (Blueprint $table) {
            $table->id('claim_id');
            $table->unsignedBigInteger('rental_id');
            $table->unsignedBigInteger('damage_id')->unique()->comment('1 damage report hanya memiliki 1 klaim asuransi');
            $table->string('claim_number', 30)->unique();
            $table->string('insurance_provider', 100);
            $table->string('policy_number', 50);
            $table->dateTime('claim_date')->useCurrent();
            $table->decimal('claim_amount', 12, 2);
            $table->decimal('approved_amount', 12, 2)->default(0);
            $table->enum('status', ['draft', 'submitted', 'under_review', 'approved', 'rejected', 'paid'])
                ->default('draft');
            $table->dateTime('approved_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('rental_id')->references('rental_id')->on('tr_rental')->cascadeOnDelete();
            $table->foreign('damage_id')->references('damage_id')->on('tr_damage_report')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tr_insurance_claim');
    }
};