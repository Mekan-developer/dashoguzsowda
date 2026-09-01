<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Рекламная новость вела на «профиль пользователя», но публичной карточки
 * пользователя в мобильном API нет и не планируется: продавца показывает
 * витрина магазина. Тип ссылки переименован profile → store, мобилка
 * открывает его через существующий GET /v1/stores/{id}.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ad_link_id у таких новостей указывал на users.id — сущность сменилась,
        // поэтому переносим только тип, а ID админ перевыбирает в форме
        DB::table('news')->where('ad_link_type', 'profile')->update([
            'ad_link_type' => 'store',
            'ad_link_id'   => null,
        ]);
    }

    public function down(): void
    {
        DB::table('news')->where('ad_link_type', 'store')->update([
            'ad_link_type' => 'profile',
            'ad_link_id'   => null,
        ]);
    }
};
