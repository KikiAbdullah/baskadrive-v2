<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('m_workshop', function (Blueprint $table) {
            $table->id('workshop_id');
            $table->string('name', 100);
            $table->text('address')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('contact_person', 100)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('m_workshop');
    }
};
