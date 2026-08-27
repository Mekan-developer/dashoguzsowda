<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Магазин пользователя (mobile_docs/BACKEND_API.md §1, §2 — поле `store` в профиле
 * и публичная витрина /v1/stores/*). Один магазин на пользователя, доступен только
 * тарифам с can_have_store (сейчас — Premium). is_popular/sort_order — ручное
 * кураторство из админки для главной страницы мобильного приложения.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete()->unique();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->string('logo')->nullable();
            $table->boolean('is_popular')->default(false);
            $table->integer('sort_order')->nullable();
            $table->timestamps();

            $table->index(['is_popular', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stores');
    }
};
