<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Заявка на платный тариф. Оплата идёт наличными на руки админу, а не онлайн,
 * поэтому PUT /v1/profile/subscription больше не выдаёт тариф сразу: он создаёт
 * заявку, а тариф активирует админ после того, как получит деньги.
 *
 * amount фиксируется на момент подачи: между заявкой и оплатой цена тарифа
 * в справочнике может измениться, а человек договаривался о той, что видел.
 *
 * Одна незакрытая заявка на пользователя — ограничение уровня приложения
 * (TariffRequestService), а не частичный UNIQUE-индекс: MySQL его не
 * поддерживает, а сравнивать по NULL-статусу — хуже, чем явная проверка.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tariff_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tariff_id')->constrained();
            $table->decimal('amount', 10, 2);
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('comment')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tariff_requests');
    }
};
