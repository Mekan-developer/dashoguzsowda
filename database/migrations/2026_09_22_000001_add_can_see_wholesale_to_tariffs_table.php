<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Право видеть опт — отдельный флаг тарифа, как can_have_store.
 * Не привязываемся к имени «Premium» / is_free: админ включает тоглом.
 * Бэкофилл: у кого уже есть магазин (can_have_store), оставляем опт включённым.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tariffs', function (Blueprint $table) {
            $table->boolean('can_see_wholesale')->default(false)->after('can_have_store');
        });

        DB::table('tariffs')->where('can_have_store', true)->update(['can_see_wholesale' => true]);
    }

    public function down(): void
    {
        Schema::table('tariffs', function (Blueprint $table) {
            $table->dropColumn('can_see_wholesale');
        });
    }
};
