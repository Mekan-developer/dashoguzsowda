<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Бесплатный тариф бессрочен: срок действия к нему не применяется, пользователь
 * сидит на нём столько, сколько нужно. Поэтому `duration_days` становится
 * nullable (null = бессрочно) и обнуляется у бесплатного тарифа, а у тех, кому
 * он уже выдан, снимается `tariff_ends_at` — иначе по его истечении
 * `activeTariff()` каждый раз ходил бы в БД за тем же самым бесплатным тарифом.
 *
 * Платные тарифы как были, так и остаются со сроком в днях.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tariffs', function (Blueprint $table) {
            $table->unsignedInteger('duration_days')->nullable()->change();
        });

        DB::table('tariffs')->where('is_free', true)->update(['duration_days' => null]);

        $freeIds = DB::table('tariffs')->where('is_free', true)->pluck('id');

        if ($freeIds->isNotEmpty()) {
            DB::table('users')->whereIn('tariff_id', $freeIds)->update(['tariff_ends_at' => null]);
        }
    }

    public function down(): void
    {
        DB::table('tariffs')->whereNull('duration_days')->update(['duration_days' => 30]);

        Schema::table('tariffs', function (Blueprint $table) {
            $table->unsignedInteger('duration_days')->default(30)->change();
        });
    }
};
