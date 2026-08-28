<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Магазин из «витрины профиля» становится торговой точкой: адрес (регион/город/
 * район), вид торговли и доставка.
 *
 * Опт и розница — два независимых флага, а не enum: торговать «оптом и в розницу»
 * — обычное дело, и enum заставил бы владельца заводить второй магазин ради
 * одного прайса. Хотя бы один флаг обязан быть поднят (проверяется в Form Request).
 *
 * has_delivery без цены и сроков: по решению команды покупатель и владелец
 * созваниваются и договариваются сами, система стоимость доставки не считает.
 *
 * status/rejection_reason_id — магазин публикуется в витрину (логотип, название,
 * телефон), значит проходит модерацию как остальной UGC.
 * is_active — гасится, когда у владельца кончается тариф с can_have_store:
 * магазин пропадает из выдачи, но не удаляется, а его объявления остаются
 * обычными объявлениями.
 *
 * Адрес и телефон nullable в схеме, но обязательны в Form Request: на проде уже
 * есть магазины, заведённые без них, — их бэкофиллим данными владельца, а те,
 * у кого и у владельца пусто, дозаполнятся при первом же сохранении.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->foreignId('region_id')->nullable()->after('category_id')->constrained()->nullOnDelete();
            $table->foreignId('city_id')->nullable()->after('region_id')->constrained()->nullOnDelete();
            $table->foreignId('district_id')->nullable()->after('city_id')->constrained()->nullOnDelete();

            $table->boolean('sells_retail')->default(true)->after('address');
            $table->boolean('sells_wholesale')->default(false)->after('sells_retail');
            $table->boolean('has_delivery')->default(false)->after('sells_wholesale');

            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending')->after('logo');
            $table->foreignId('rejection_reason_id')->nullable()->after('status')->constrained()->nullOnDelete();
            $table->boolean('is_active')->default(true)->after('rejection_reason_id');

            $table->index(['status', 'is_active']);
        });

        // Магазины, созданные до появления модерации, уже видны в мобилке —
        // отправлять их в очередь задним числом нельзя.
        DB::table('stores')->update(['status' => 'approved']);

        $this->backfillAddressFromOwners();
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropIndex(['status', 'is_active']);
            $table->dropConstrainedForeignId('region_id');
            $table->dropConstrainedForeignId('city_id');
            $table->dropConstrainedForeignId('district_id');
            $table->dropConstrainedForeignId('rejection_reason_id');
            $table->dropColumn([
                'sells_retail', 'sells_wholesale', 'has_delivery', 'status', 'is_active',
            ]);
        });
    }

    /**
     * Адрес и телефон магазина берём у владельца — это те же данные, которые он
     * уже указал в профиле. Через PHP, а не UPDATE ... JOIN: магазинов единицы,
     * зато миграция одинаково работает на MySQL и на sqlite в тестах.
     */
    private function backfillAddressFromOwners(): void
    {
        $stores = DB::table('stores')->select('id', 'user_id', 'phone')->get();

        foreach ($stores as $store) {
            $owner = DB::table('users')
                ->select('region_id', 'city_id', 'district_id', 'phone')
                ->find($store->user_id);

            if (! $owner) {
                continue;
            }

            DB::table('stores')->where('id', $store->id)->update([
                'region_id'   => $owner->region_id,
                'city_id'     => $owner->city_id,
                'district_id' => $owner->district_id,
                'phone'       => $store->phone ?: $owner->phone,
            ]);
        }
    }
};
