<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Remediasi Fleet (audit 15092026 - audit_fleet.md):
 *
 * FLE-02: kontrak status kerusakan disatukan — enum DB diperluas agar mencakup
 *         status alur kerja yang sebelumnya hanya ada di controller/UI (inspected,
 *         approved, in_repair, rejected, closed) SELAIN status skema awal yang tetap
 *         valid untuk data lama (assessment, repair_in_progress, claimed_insurance,
 *         written_off). Sesuai App\Models\DamageReport::STATUSES.
 * FLE-07: tr_fine.damage_id — identitas kewajiban tagihan kerusakan; deduplikasi
 *         penagihan tidak lagi bergantung pada LIKE deskripsi bebas.
 * FLE-12: status klaim diperluas dengan "closed" dan kolom paid_at (stempel waktu
 *         pencairan; paid_date tetap ada karena sudah dipakai modul lain).
 *
 * Catatan driver: migrasi memakai ->change() yang di Laravel 10+ berjalan natif
 * pada MySQL, Postgres, dan SQLite (tanpa doctrine/dbal) sehingga aman untuk
 * environment uji (SQLite) dan produksi (MySQL).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tr_damage_report', function (Blueprint $table) {
            $table->enum('status', [
                'reported', 'inspected', 'assessment', 'approved', 'in_repair',
                'repair_in_progress', 'repaired', 'rejected', 'claimed_insurance',
                'written_off', 'closed',
            ])->default('reported')->change();
        });

        Schema::table('tr_insurance_claim', function (Blueprint $table) {
            $table->enum('status', ['draft', 'submitted', 'under_review', 'approved', 'rejected', 'paid', 'closed'])
                ->default('draft')->change();

            // Selaras dengan validasi & form: nomor polis boleh kosong saat pengajuan.
            $table->string('policy_number', 50)->nullable()->change();

            if (! Schema::hasColumn('tr_insurance_claim', 'paid_at')) {
                $table->dateTime('paid_at')->nullable()->after('paid_date');
            }
        });

        Schema::table('tr_fine', function (Blueprint $table) {
            if (! Schema::hasColumn('tr_fine', 'damage_id')) {
                $table->unsignedBigInteger('damage_id')->nullable()->after('return_id');
            }
        });

        Schema::table('tr_fine', function (Blueprint $table) {
            if (! $this->foreignKeyExists('tr_fine', 'tr_fine_damage_id_foreign')) {
                $table->foreign('damage_id')->references('damage_id')->on('tr_damage_report')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('tr_fine', function (Blueprint $table) {
            if ($this->foreignKeyExists('tr_fine', 'tr_fine_damage_id_foreign')) {
                $table->dropForeign(['damage_id']);
            }

            if (Schema::hasColumn('tr_fine', 'damage_id')) {
                $table->dropColumn('damage_id');
            }
        });

        Schema::table('tr_insurance_claim', function (Blueprint $table) {
            if (Schema::hasColumn('tr_insurance_claim', 'paid_at')) {
                $table->dropColumn('paid_at');
            }

            // Kembali ke kontrak awal — hanya aman bila tidak ada data berstatus closed.
            $table->enum('status', ['draft', 'submitted', 'under_review', 'approved', 'rejected', 'paid'])
                ->default('draft')->change();
        });

        Schema::table('tr_damage_report', function (Blueprint $table) {
            $table->enum('status', ['reported', 'assessment', 'repair_in_progress', 'repaired', 'claimed_insurance', 'written_off'])
                ->default('reported')->change();
        });
    }

    private function foreignKeyExists(string $table, string $name): bool
    {
        try {
            $fks = Schema::getForeignKeys($table);

            return in_array($name, array_column($fks, 'name'), true);
        } catch (Throwable $e) {
            return false;
        }
    }
};
