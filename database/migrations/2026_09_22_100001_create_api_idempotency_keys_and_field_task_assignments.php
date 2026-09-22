<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fondasi API mobile `/api/v1/field` (konsep BaskaDrive Operator, §9.2):
 *
 * - `api_idempotency_keys` — dedup tulis offline-first: mobile mengirim
 *   `client_uuid` (UUID v4) pada setiap request tulis; server menolak
 *   duplikat dengan 409 ALREADY_PROCESSED (kontrak B.7).
 * - `field_task_assignments` — penugasan tugas operator per sewa tanpa
 *   mengubah skema inti `tr_rental` (konsep §9.2 terakhir).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_idempotency_keys', function (Blueprint $table) {
            $table->string('client_uuid', 64)->primary();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('endpoint', 191)->index();
            $table->string('http_status', 8)->default('200');
            $table->json('result')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('field_task_assignments', function (Blueprint $table) {
            $table->unsignedBigInteger('assignment_id')->primary();
            $table->unsignedBigInteger('rental_id')->index();
            $table->unsignedBigInteger('assigned_to')->index();
            $table->timestamp('scheduled_at')->nullable();
            // pending → done (sewa selesai ditangani) / skipped (dipindah tangan)
            $table->string('status', 20)->default('pending')->index();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('rental_id')->references('rental_id')->on('tr_rental')->cascadeOnDelete();
            $table->foreign('assigned_to')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['rental_id', 'assigned_to']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('field_task_assignments');
        Schema::dropIfExists('api_idempotency_keys');
    }
};
