<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Товар магазина — это объявление, а не отдельная сущность: у listings уже есть
 * медиа-пайплайн, модерация, поиск, избранное, жалобы, boost и лимиты тарифа,
 * и второй раз всё это строить незачем.
 *
 * store_id проставляется автоматически при создании объявления автором, у
 * которого есть магазин, — витрина /v1/stores/{id}/listings переезжает с
 * фильтра по user_id на store_id (точнее и переживёт появление филиалов).
 *
 * district_id — адрес объявления: у объявления магазина он копируется из
 * магазина, у обычного пользователя заполняется им самим.
 *
 * stock_qty — одно поле на три состояния: null = «в наличии» (владелец не ведёт
 * учёт), 0 = «нет в наличии», N = «N шт». Когда на мобилке появится корзина,
 * она будет уменьшать это же поле, без миграции данных.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->foreignId('store_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            $table->foreignId('district_id')->nullable()->after('city_id')->constrained()->nullOnDelete();

            $table->decimal('wholesale_price', 12, 2)->nullable()->after('price');
            $table->unsignedInteger('min_order_qty')->nullable()->after('wholesale_price');
            $table->unsignedInteger('stock_qty')->nullable()->after('min_order_qty');

            $table->index(['store_id', 'status']);
        });

        // Витрина магазина до сих пор показывала все объявления владельца —
        // сохраняем ровно ту же выдачу после перехода на store_id.
        foreach (DB::table('stores')->select('id', 'user_id')->get() as $store) {
            DB::table('listings')
                ->where('user_id', $store->user_id)
                ->update(['store_id' => $store->id]);
        }
    }

    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->dropIndex(['store_id', 'status']);
            $table->dropConstrainedForeignId('store_id');
            $table->dropConstrainedForeignId('district_id');
            $table->dropColumn(['wholesale_price', 'min_order_qty', 'stock_qty']);
        });
    }
};
