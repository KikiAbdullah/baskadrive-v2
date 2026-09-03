<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('m_employee', function (Blueprint $table) {
            $table->id('employee_id');
            $table->string('first_name', 50);
            $table->string('last_name', 50);
            $table->string('email', 100)->unique();
            $table->string('phone', 20)->nullable();
            $table->string('position', 50)->comment('Kasir, Manager, Mekanik, Admin, Direktur');
            $table->date('hire_date');
            $table->string('username', 50)->unique();
            $table->string('password_hash', 255)->comment('bcrypt/sha256');
            $table->enum('role', ['admin', 'manager', 'cashier', 'mechanic', 'accountant', 'director']);
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('m_employee');
    }
};
