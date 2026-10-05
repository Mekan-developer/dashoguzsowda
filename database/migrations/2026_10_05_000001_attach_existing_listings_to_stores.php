<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Открыл магазин — все прежние объявления автора становятся его товарами
 * (StoreService::attachExistingListings). Новые магазины делают это сами при
 * создании; здесь — разовая привязка для магазинов, открытых раньше.
 * Адрес магазина переносится, только если у него заданы регион и город.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('stores')->orderBy('id')->each(function (object $store) {
            $address = $store->region_id && $store->city_id
                ? ['region_id' => $store->region_id, 'city_id' => $store->city_id, 'district_id' => $store->district_id]
                : [];

            DB::table('listings')
                ->where('user_id', $store->user_id)
                ->whereNull('store_id')
                ->update(['store_id' => $store->id, ...$address]);
        });
    }

    public function down(): void
    {
        // Какие объявления привязаны здесь, а какие при создании — уже не отличить
    }
};
