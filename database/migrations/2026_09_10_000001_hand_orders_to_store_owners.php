<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Заказ ведёт владелец магазина, а не админ (решение заказчика).
 *
 * Раньше решение по заказу принимал админ: он обзванивал магазины, подтверждал
 * наличие и вёз товар сам — отсюда и названия колонок. Теперь заказ уходит
 * прямо продавцу: он подтверждает наличие, он же доставляет и получает деньги,
 * а админ только смотрит, что и кому продано. Поэтому колонки переименованы —
 * «обработал админ» превратилось в «кто принял решение по заказу»:
 *
 *   admin_comment → decision_comment (причина отказа продавца)
 *   processed_by  → decided_by       (владелец магазина, а с отменой — никто)
 *   processed_at  → decided_at
 *
 * Данные сохраняются: это переименование, а не пересоздание.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->renameColumn('admin_comment', 'decision_comment');
            $table->renameColumn('processed_by', 'decided_by');
            $table->renameColumn('processed_at', 'decided_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->renameColumn('decision_comment', 'admin_comment');
            $table->renameColumn('decided_by', 'processed_by');
            $table->renameColumn('decided_at', 'processed_at');
        });
    }
};
