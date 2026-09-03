<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tr_damage_photo', function (Blueprint $table) {
            $table->id('photo_id');
            $table->unsignedBigInteger('damage_id');
            $table->string('photo_url', 255);
            $table->string('caption', 100)->nullable();
            $table->timestamp('uploaded_at')->useCurrent();

            $table->foreign('damage_id')->references('damage_id')->on('tr_damage_report')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tr_damage_photo');
    }
};