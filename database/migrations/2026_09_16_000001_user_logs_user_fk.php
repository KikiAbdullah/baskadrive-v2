<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * user_logs.user_id: nullable + FK nullOnDelete agar hapus user tidak
     * menggagalkan / meninggalkan log yatim (SYS-06). MySQL only (tanpa
     * doctrine/dbal); SQLite (tes) dilewati seperti migrasi trigger.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        $userTable = (new App\Models\User)->getTable();

        // Bersihkan yatim lebih dulu agar penambahan FK tidak gagal
        DB::table('user_logs')
            ->whereNotNull('user_id')
            ->whereNotIn('user_id', function ($q) use ($userTable) {
                $q->select('id')->from($userTable);
            })
            ->update(['user_id' => null]);

        DB::statement('ALTER TABLE `user_logs` MODIFY `user_id` BIGINT UNSIGNED NULL');
        DB::statement(
            'ALTER TABLE `user_logs` ADD CONSTRAINT `user_logs_user_id_foreign` ' .
            "FOREIGN KEY (`user_id`) REFERENCES `{$userTable}` (`id`) ON DELETE SET NULL"
        );
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE `user_logs` DROP FOREIGN KEY `user_logs_user_id_foreign`');
    }
};
