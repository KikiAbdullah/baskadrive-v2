<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('m_brand', function (Blueprint $table) {
            $table->id('brand_id');
            $table->string('brand_name', 50)->unique()->comment('Nama merek (Toyota, Honda, dll.)');
            $table->string('logo_url', 255)->nullable()->comment('URL logo merek');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('m_brand');
    }
};
