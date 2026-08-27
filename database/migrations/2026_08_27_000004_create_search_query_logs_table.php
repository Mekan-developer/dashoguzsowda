<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Счётчик поисковых запросов по всему сайту (в т.ч. гости) — источник для
 * GET /v1/search/popular (mobile_docs/BACKEND_API.md §4). Не путать с search_recents:
 * та таблица — персональная история (максимум 8 строк на юзера, только авторизованные).
 *
 * query_key — нормализованный (trim + lower) вариант запроса, как в search_recents,
 * по той же причине: регистронезависимость unique-индекса зависит от collation БД.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_query_logs', function (Blueprint $table) {
            $table->id();
            $table->string('query', 191);
            $table->string('query_key', 191);
            $table->unsignedInteger('hits')->default(1);
            $table->timestamp('last_searched_at')->nullable();
            $table->timestamps();

            $table->unique('query_key');
            $table->index(['hits', 'last_searched_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_query_logs');
    }
};
