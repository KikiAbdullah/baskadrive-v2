<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('m_driver', function (Blueprint $table) {
            $table->id('driver_id');
            $table->string('first_name', 50);
            $table->string('last_name', 50);
            $table->string('license_number', 30)->unique();
            $table->date('license_expiry')->nullable();
            $table->string('phone', 20)->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('m_driver');
    }
};
