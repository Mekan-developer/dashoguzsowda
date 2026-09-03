<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Заказы товаров магазинов.
 *
 * Заказать можно только товар магазина с has_delivery: без доставки покупатель
 * и продавец созваниваются и договариваются сами, как и раньше, — корзина в
 * таком случае в мобилке не показывается.
 *
 * Три уровня вместо одного, потому что у заказа три разных «хозяина»:
 *   orders     — то, что оформил покупатель: контакты, адрес доставки, статус
 *                у админа (он звонит, уточняет наличие и везёт);
 *   suborders  — часть заказа, относящаяся к одному магазину: свой статус,
 *                который ставит владелец магазина, и своя сумма. Заказ из
 *                трёх магазинов — один orders и три suborders;
 *   order_items— позиции со снимком названия и цены на момент заказа.
 *
 * Снимок цены обязателен: между оформлением и подтверждением админа владелец
 * может поменять ценник, а договаривались о той цене, которую видел покупатель
 * (та же логика, что и в tariff_requests.amount).
 *
 * listing_id / store_id — nullOnDelete: объявление и магазин админ может
 * удалить, а история заказов должна пережить это и остаться читаемой (для того
 * в позициях и лежит title).
 *
 * stock_taken — списан ли остаток по этой позиции. Остаток уходит в минус не
 * при оформлении, а при подтверждении админом (до этого заказ ничего не
 * резервирует), а флаг нужен, чтобы при отмене вернуть ровно те позиции, по
 * которым списание было: у товара с stock_qty = null учёт не ведётся и
 * возвращать там нечего.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // pending   — оформлен, ждёт, пока админ обзвонит магазины
            // approved  — админ подтвердил наличие, подзаказы ушли владельцам
            // completed — доставлен и оплачен на месте
            // rejected  — админ отказал (товара нет, покупатель недоступен)
            // canceled  — отменён покупателем (пока pending) или админом
            //
            // Промежуточного «в пути» нет намеренно: доставку ведёт админ и
            // отмечает результат, а отдельный статус пришлось бы держать
            // в актуальном состоянии руками ради нескольких часов жизни заказа.
            $table->enum('status', [
                'pending', 'approved', 'completed', 'rejected', 'canceled',
            ])->default('pending');

            $table->decimal('total', 12, 2)->default(0);

            // Контакты получателя: по умолчанию из профиля, но заказать могут
            // и на другого человека — поэтому отдельные поля, а не связь
            $table->string('contact_name')->nullable();
            $table->string('phone');

            $table->foreignId('region_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('city_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('district_id')->nullable()->constrained()->nullOnDelete();
            $table->string('address');
            $table->text('comment')->nullable();

            // Комментарий админа: причина отказа или пометка после обзвона
            $table->text('admin_comment')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();

            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('suborders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_id')->nullable()->constrained()->nullOnDelete();
            // Владелец на момент заказа: магазин могут удалить, а понять,
            // кому показывать подзаказ и кому слать push, нужно и после этого
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // pending  — ждёт ответа владельца (он отвечает первым, до админа)
            // accepted — товар есть и магазин его отдаёт: ответил сам владелец
            //            либо админ подтвердил заказ после разговора с ним
            // declined — владелец отказался, позиции не поедут и не списываются
            // canceled — заказ закрыт админом (отказ/отмена) раньше, чем магазин
            //            ответил; без этого статуса часть висела бы в pending
            //            даже у давно завершённого заказа
            $table->enum('status', ['pending', 'accepted', 'declined', 'canceled'])->default('pending');

            $table->decimal('subtotal', 12, 2)->default(0);
            $table->text('comment')->nullable();
            $table->timestamp('responded_at')->nullable();

            $table->timestamps();

            $table->unique(['order_id', 'store_id']);
            $table->index(['store_id', 'status']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            // order_id продублирован рядом с suborder_id намеренно: сумму и
            // состав заказа целиком админка читает без join через suborders
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('suborder_id')->constrained()->cascadeOnDelete();
            $table->foreignId('listing_id')->nullable()->constrained()->nullOnDelete();

            $table->string('title');
            $table->decimal('unit_price', 12, 2);
            // Цена взята оптовая (qty >= min_order_qty), а не розничная
            $table->boolean('is_wholesale')->default(false);
            $table->unsignedInteger('qty');
            $table->decimal('total', 12, 2);
            $table->boolean('stock_taken')->default(false);

            $table->timestamps();

            $table->index('listing_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('suborders');
        Schema::dropIfExists('orders');
    }
};
