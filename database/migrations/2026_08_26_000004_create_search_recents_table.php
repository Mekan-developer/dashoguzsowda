<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * История поиска пользователя (mobile_docs/CLAUDE_CODE_BACKEND_PLAN.md, задача 2).
 * Гость хранит историю только на устройстве — здесь лежит история авторизованных.
 *
 * query_key — нормализованный (trim + lower) вариант запроса. Отдельная колонка,
 * а не unique по `query`, потому что регистронезависимость unique-индекса зависит
 * от collation: в MySQL utf8mb4_0900_ai_ci она есть, в SQLite для кириллицы нет,
 * и дедупликация вела бы себя по-разному в проде и в тестах.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_recents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('query', 191);
            $table->string('query_key', 191);
            $table->timestamps();

            $table->unique(['user_id', 'query_key']);
            $table->index(['user_id', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_recents');
    }
};
