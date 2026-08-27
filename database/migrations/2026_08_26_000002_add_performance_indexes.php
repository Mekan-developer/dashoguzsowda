<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Индексы под запросы, которые выполняются чаще всего.
 *
 * До этой миграции индексы существовали только там, где их создал
 * constrained() (внешние ключи) плюс уникальные пары favorites/video_likes.
 * Главная лента приложения делала полный скан listings с сортировкой,
 * а счётчики в топбаре админки — полный скан messages/reviews/complaints
 * на каждый Inertia-запрос.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            // Публичная лента: WHERE status='approved' ORDER BY is_boosted DESC, created_at DESC
            // (ListingRepository::paginateForApi). Ведущая колонка заодно обслуживает
            // countByStatus() для счётчиков модерации.
            $table->index(['status', 'is_boosted', 'created_at'], 'listings_feed_index');

            // «Мои объявления» и подсчёт занятой квоты тарифа
            // (paginateByUser, CheckTariffLimitAction::countByUserAndStatuses).
            $table->index(['user_id', 'status'], 'listings_user_status_index');
        });

        Schema::table('videos', function (Blueprint $table) {
            // Публичная лента роликов + счётчики модерации.
            $table->index(['status', 'created_at'], 'videos_feed_index');

            // «Мои ролики» и квота тарифа (CheckVideoLimitAction).
            $table->index(['user_id', 'status'], 'videos_user_status_index');
        });

        Schema::table('messages', function (Blueprint $table) {
            // Пометка прочитанным и unread_count по конкретному диалогу
            // (ChatRepository::markAsRead, getDialogs).
            $table->index(['user_id', 'sender', 'is_read'], 'messages_dialog_index');

            // Глобальный счётчик непрочитанных в топбаре — считается на каждый
            // запрос админки (HandleInertiaRequests::share).
            $table->index(['sender', 'is_read'], 'messages_unread_index');
        });

        Schema::table('reviews', function (Blueprint $table) {
            $table->index('status', 'reviews_status_index');
        });

        Schema::table('complaints', function (Blueprint $table) {
            $table->index('status', 'complaints_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->dropIndex('listings_feed_index');
            $table->dropIndex('listings_user_status_index');
        });

        Schema::table('videos', function (Blueprint $table) {
            $table->dropIndex('videos_feed_index');
            $table->dropIndex('videos_user_status_index');
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex('messages_dialog_index');
            $table->dropIndex('messages_unread_index');
        });

        Schema::table('reviews', function (Blueprint $table) {
            $table->dropIndex('reviews_status_index');
        });

        Schema::table('complaints', function (Blueprint $table) {
            $table->dropIndex('complaints_status_index');
        });
    }
};
