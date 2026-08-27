<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Мобильное приложение (mobile_docs/BACKEND_API.md §3) ждёт англ. slug тарифа
 * (Basic/Standard/Premium) для GET /v1/tariffs и PUT /v1/profile/subscription —
 * в БД до сих пор только name_ru/name_tk. can_have_store — тариф, дающий право
 * на витрину-магазин (mobile_docs/BACKEND_API.md §2, canHaveStore).
 *
 * Бэкофилл — по is_free/name_ru, а не по id: на проде эти 3 строки уже есть,
 * их id менять нельзя. Кастомные тарифы, заведённые админом сверх этих трёх,
 * останутся без slug (name = null) и просто не попадут в мобильный каталог,
 * пока админ не проставит slug вручную.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tariffs', function (Blueprint $table) {
            $table->string('name')->nullable()->unique()->after('id');
            $table->boolean('can_have_store')->default(false)->after('is_free');
        });

        DB::table('tariffs')->where('is_free', true)->update(['name' => 'Basic']);
        DB::table('tariffs')->where('is_free', false)->where('name_ru', 'like', '%тандарт%')
            ->update(['name' => 'Standard']);
        DB::table('tariffs')->where('is_free', false)->where('name_ru', 'like', '%рем%')
            ->update(['name' => 'Premium', 'can_have_store' => true]);
    }

    public function down(): void
    {
        Schema::table('tariffs', function (Blueprint $table) {
            $table->dropColumn(['name', 'can_have_store']);
        });
    }
};
