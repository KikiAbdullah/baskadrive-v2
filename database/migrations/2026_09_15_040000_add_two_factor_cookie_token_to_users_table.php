<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('two_factor_cookie_hash', 255)->nullable()->after('token_2fa_expires_at');
            $table->timestamp('two_factor_cookie_expires_at')->nullable()->after('two_factor_cookie_hash');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['two_factor_cookie_hash', 'two_factor_cookie_expires_at']);
        });
    }
};
