<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Способ оплаты заказа: чем покупатель рассчитается с продавцом.
 *
 * Онлайн-оплаты в проекте нет — деньги идут мимо системы, при доставке или
 * переводом напрямую продавцу. Поэтому способ оплаты здесь не платёжный шлюз,
 * а договорённость: покупатель выбирает её при оформлении, продавец видит в
 * заказе и приезжает готовым.
 *
 * Справочник, а не enum: набор способов ведёт админ (решение заказчика) — как
 * rejection_reasons и complaint_reasons, с теми же name_ru/name_tk и is_active.
 * Кодов у строк нет намеренно: мобилка рисует название из справочника, значит
 * добавленный админом способ заработает без релиза приложения.
 *
 * payment_method_store — какие способы принимает конкретный магазин: платит
 * покупатель ему лично, значит и условия его. Минимум один способ у магазина
 * есть всегда (проверяется в Form Request, при создании проставляется сам).
 *
 * orders.payment_method_id — выбор покупателя, nullable: поле необязательное,
 * пусто = «договорятся по телефону», как и раньше. nullOnDelete — заказ
 * переживает удаление строки справочника; выводить способ из обращения
 * штатно нужно через is_active, тогда история остаётся читаемой.
 */
return new class extends Migration
{
    /** Стартовый набор: без него магазину нечего принять, а покупателю — выбрать. */
    private const SEED = [
        ['name_ru' => 'Наличные при получении', 'name_tk' => 'Alanyňda nagt'],
        ['name_ru' => 'Перевод на карту',       'name_tk' => 'Karta geçirim'],
        ['name_ru' => 'Терминал при доставке',  'name_tk' => 'Eltip berlende terminal'],
    ];

    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('name_ru');
            $table->string('name_tk');
            $table->boolean('is_active')->default(true);
            // Порядок в списке выбора: наличные идут первыми и потому же
            // достаются магазину как способ по умолчанию
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        Schema::create('payment_method_store', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_method_id')->constrained()->cascadeOnDelete();

            $table->unique(['store_id', 'payment_method_id']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('payment_method_id')->nullable()->after('comment')
                ->constrained()->nullOnDelete();
        });

        $this->seedMethods();
        $this->backfillStores();
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payment_method_id');
        });

        Schema::dropIfExists('payment_method_store');
        Schema::dropIfExists('payment_methods');
    }

    private function seedMethods(): void
    {
        $now = now();
        $order = 0;

        foreach (self::SEED as $method) {
            DB::table('payment_methods')->insert([
                ...$method,
                'is_active'  => true,
                'sort_order' => $order++,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * Магазины, заведённые до появления справочника, уже торгуют — оставить их
     * без единого способа оплаты нельзя: покупателю нечего будет выбрать, а
     * форма правки магазина потребует минимум один и не даст сохраниться.
     * Ставим первый способ (наличные) — так и работало до сих пор.
     */
    private function backfillStores(): void
    {
        $default = DB::table('payment_methods')->orderBy('sort_order')->orderBy('id')->value('id');

        if (! $default) {
            return;
        }

        $rows = DB::table('stores')->pluck('id')
            ->map(fn ($storeId) => ['store_id' => $storeId, 'payment_method_id' => $default])
            ->all();

        if ($rows !== []) {
            DB::table('payment_method_store')->insert($rows);
        }
    }
};
