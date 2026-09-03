<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tr_rental_detail', function (Blueprint $table) {
            $table->id('detail_id');
            $table->unsignedBigInteger('rental_id');
            $table->enum('item_type', ['extra_driver', 'child_seat', 'gps', 'toll', 'fuel', 'other']);
            $table->string('item_name', 100)->nullable();
            $table->integer('quantity')->default(1);
            $table->decimal('unit_price', 10, 2);
            $table->decimal('total_price', 10, 2)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('rental_id')->references('rental_id')->on('tr_rental')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tr_rental_detail');
    }
};