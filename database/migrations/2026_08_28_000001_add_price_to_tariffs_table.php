<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Тариф оплачивается наличными на руки админу, а не онлайн, поэтому цена нужна
 * в самой системе: мобилка показывает её в каталоге планов, а админ сверяет
 * принятую сумму с заявкой (`tariff_requests.amount`).
 *
 * Бесплатный тариф остаётся с price = 0.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tariffs', function (Blueprint $table) {
            $table->decimal('price', 10, 2)->default(0)->after('name_tk');
        });
    }

    public function down(): void
    {
        Schema::table('tariffs', function (Blueprint $table) {
            $table->dropColumn('price');
        });
    }
};
