<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Флаг прохождения онбординга (CLAUDE_CODE_BACKEND_PLAN.md, задача 3).
 *
 * Сам онбординг (выбор языка и темы) остаётся device-local — синхронизируется
 * только сам факт прохождения, чтобы пользователь, уже прошедший его на другом
 * устройстве, не проходил заново. Колонка на users, а не отдельная таблица:
 * поле ровно одно.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('onboarding_completed')->default(false)->after('locale');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('onboarding_completed');
        });
    }
};
