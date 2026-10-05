<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Колонка nullable: ролики, загруженные до появления категорий, остаются без
 * неё (VideoResource и админка это переживают), а у новых категорию требует
 * валидация. NOT NULL на непустой таблице заполнял старые строки нулём, и
 * внешний ключ на categories падал (1452).
 *
 * Миграция переживает и прошлый неудачный запуск: MySQL не откатывает DDL,
 * поэтому колонка могла уже добавиться — с нулями и без ключа.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('videos', 'category_id')) {
            Schema::table('videos', function (Blueprint $table) {
                $table->unsignedBigInteger('category_id')->nullable()->change();
            });

            DB::table('videos')
                ->whereNotIn('category_id', DB::table('categories')->select('id'))
                ->update(['category_id' => null]);
        } else {
            Schema::table('videos', function (Blueprint $table) {
                $table->unsignedBigInteger('category_id')->nullable()->after('user_id');
            });
        }

        $hasForeign = collect(Schema::getForeignKeys('videos'))
            ->contains(fn ($fk) => $fk['columns'] === ['category_id']);

        Schema::table('videos', function (Blueprint $table) use ($hasForeign) {
            if (! $hasForeign) {
                $table->foreign('category_id')->references('id')->on('categories')->nullOnDelete();
            }

            // Лента с фильтром по категории: WHERE category_id=? AND status='approved' ORDER BY created_at DESC
            if (! Schema::hasIndex('videos', 'videos_category_feed_index')) {
                $table->index(['category_id', 'status', 'created_at'], 'videos_category_feed_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->dropIndex('videos_category_feed_index');
            $table->dropConstrainedForeignId('category_id');
        });
    }
};
