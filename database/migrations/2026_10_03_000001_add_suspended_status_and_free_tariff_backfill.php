<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Истёк платный тариф → пользователь переходит на бесплатный, а объявления и
 * ролики сверх его лимитов получают статус `suspended`: публичная выдача
 * везде фильтрует `status = approved`, поэтому они пропадают сами. Оплатит
 * тариф снова — вернутся в approved (CLAUDE.md → «Тарифы — оплата наличными»).
 *
 * Заодно бесплатный тариф проставляется явно тем, у кого tariff_id пуст:
 * раньше он только подставлялся при чтении (User::activeTariff), и в базе
 * таких пользователей не было видно — ни в счётчике тарифа, ни в фильтре.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->enum('status', ['pending', 'approved', 'rejected', 'suspended'])->default('pending')->change();
        });

        Schema::table('videos', function (Blueprint $table) {
            $table->enum('status', ['pending', 'approved', 'rejected', 'suspended'])->default('pending')->change();
        });

        $freeId = DB::table('tariffs')->where('is_free', true)->value('id');

        if ($freeId) {
            DB::table('users')
                ->where('role', 'user')
                ->whereNull('tariff_id')
                ->update(['tariff_id' => $freeId, 'tariff_ends_at' => null]);
        }
    }

    public function down(): void
    {
        // Скрытое по тарифу было одобрено до скрытия — туда и возвращаем
        DB::table('listings')->where('status', 'suspended')->update(['status' => 'approved']);
        DB::table('videos')->where('status', 'suspended')->update(['status' => 'approved']);

        Schema::table('listings', function (Blueprint $table) {
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending')->change();
        });

        Schema::table('videos', function (Blueprint $table) {
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending')->change();
        });
    }
};
