<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Audit M-12: sambungkan satu-ke-satu akun login dengan data karyawan
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('employee_id')->nullable()->unique()->after('nowa');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('employee_id')->references('employee_id')->on('m_employee')->nullOnDelete();
        });

        // Backfill: cocokkan lewat username (sumber utama) lalu email
        $employees = DB::table('m_employee')->get();
        foreach ($employees as $emp) {
            $user = DB::table('users')
                ->whereNull('employee_id')
                ->where(fn ($q) => $q->where('username', $emp->username)->orWhere('email', $emp->email))
                ->first();

            if ($user) {
                DB::table('users')->where('id', $user->id)->update(['employee_id' => $emp->employee_id]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['employee_id']);
            $table->dropUnique(['employee_id']);
            $table->dropColumn('employee_id');
        });
    }
};
