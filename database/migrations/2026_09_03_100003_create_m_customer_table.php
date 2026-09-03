<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('m_customer', function (Blueprint $table) {
            $table->id('customer_id');
            $table->enum('customer_type', ['individual', 'corporate'])->default('individual');
            $table->string('first_name', 50);
            $table->string('last_name', 50);
            $table->string('company_name', 100)->nullable();
            $table->string('email', 100)->unique()->nullable();
            $table->string('phone', 20)->nullable();
            $table->text('address')->nullable();
            $table->string('city', 50)->nullable();
            $table->string('province', 50)->nullable();
            $table->string('postal_code', 10)->nullable();
            $table->string('country', 50)->default('Indonesia');
            $table->string('driver_license_number', 30)->unique();
            $table->date('driver_license_expiry')->nullable();
            $table->string('driver_license_photo', 255)->nullable();
            $table->string('id_card_number', 20)->unique()->nullable();
            $table->string('id_card_photo', 255)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->boolean('is_verified')->default(false);
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('m_customer');
    }
};
