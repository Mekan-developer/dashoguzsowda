<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Комиссия платформы с проданного товара.
 *
 * Процент свой у каждого магазина (админ ставит его в карточке магазина),
 * поэтому он лежит в stores, а не в настройках: договариваются с каждым
 * владельцем отдельно. У нового магазина — 0, то есть до решения админа
 * комиссия не берётся.
 *
 * Комиссия удерживается с магазина, а не добавляется покупателю: orders.total
 * остаётся суммой, которую покупатель видел в корзине, а магазин получает
 * subtotal − commission_total.
 *
 * Снимок процента лежит в suborders, как unit_price в позициях: пока админ
 * ведёт заказ, ставку магазина могут поменять, а считаться заказ должен по
 * той, что действовала в момент оформления. В позициях хранится только сумма —
 * процент по всему подзаказу один, дублировать его в каждой строке незачем,
 * а суммы нужны по отдельности из-за округления до копейки.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->decimal('commission_percent', 5, 2)->default(0)->after('has_delivery');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('commission_total', 12, 2)->default(0)->after('total');
        });

        Schema::table('suborders', function (Blueprint $table) {
            $table->decimal('commission_percent', 5, 2)->default(0)->after('subtotal');
            $table->decimal('commission_total', 12, 2)->default(0)->after('commission_percent');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->decimal('commission_amount', 12, 2)->default(0)->after('total');
        });
    }

    public function down(): void
    {
        Schema::table('stores', fn (Blueprint $table) => $table->dropColumn('commission_percent'));
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn('commission_total'));
        Schema::table('suborders', fn (Blueprint $table) => $table->dropColumn(['commission_percent', 'commission_total']));
        Schema::table('order_items', fn (Blueprint $table) => $table->dropColumn('commission_amount'));
    }
};
