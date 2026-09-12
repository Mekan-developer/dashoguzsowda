<?php

namespace App\Repositories\Interfaces;

use App\Models\PaymentMethod;
use Illuminate\Database\Eloquent\Collection;

/**
 * Справочник способов оплаты: ведёт его админ, читают админка (настройки,
 * карточка магазина) и мобильное API (набор магазина, оформление заказа).
 */
interface PaymentMethodRepositoryInterface
{
    /** Все способы, включая выключенные, — для страницы настроек. */
    public function all(): Collection;

    /** Активные способы: из них магазин собирает свой набор. */
    public function active(): Collection;

    /**
     * Способ по умолчанию — первый активный. Достаётся магазину, который
     * создан без явного выбора: без единого способа покупателю нечего выбрать.
     */
    public function defaultId(): ?int;

    public function create(array $data): PaymentMethod;

    public function update(PaymentMethod $method, array $data): PaymentMethod;

    public function delete(PaymentMethod $method): void;

    /** Сколько заказов уже сослалось на способ: удалять такой нельзя. */
    public function countOrders(PaymentMethod $method): int;
}
