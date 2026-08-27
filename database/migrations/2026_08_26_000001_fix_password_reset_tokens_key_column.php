<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Таблица создавалась с колонкой `phone` (0001_01_01_000000_create_users_table),
 * но восстановление пароля в админке идёт по email: DatabaseTokenRepository
 * пишет и читает колонку `email`. Из-за рассинхрона «Забыли пароль» падало
 * с ошибкой драйвера («no such column: email»), а три теста
 * tests/Feature/Auth/PasswordResetTest.php были стабильно красными.
 *
 * Таблица пересоздаётся, а не переименовывается: токены живут 60 минут
 * (config/auth.php → passwords.users.expire), терять нечего.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('password_reset_tokens');

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_reset_tokens');

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('phone')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }
};
