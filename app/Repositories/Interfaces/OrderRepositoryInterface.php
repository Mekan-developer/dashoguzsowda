<?php

namespace App\Repositories\Interfaces;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Suborder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface OrderRepositoryInterface
{
    /**
     * Создаёт заказ целиком: сам заказ, часть магазина и позиции.
     * Только в транзакции — половина заказа никому не нужна.
     *
     * @param  array<string, mixed>  $order
     * @param  array<int, array{store_id: int|null, user_id: int|null, subtotal: string, items: array<int, array<string, mixed>>}>  $suborders
     */
    public function create(array $order, array $suborders): Order;

    /** Заказ покупателя со всем составом; null — чужой или несуществующий. */
    public function findForUser(int $orderId, int $userId): ?Order;

    /** «Мои заказы» в мобилке: фильтр status. */
    public function paginateForUser(int $userId, array $filters, int $perPage = 20): LengthAwarePaginator;

    /** Заказ для админки — со всеми связями, 404 если нет. */
    public function find(int $orderId): Order;

    /**
     * Заказы в админке: фильтры status, search (номер / имя / телефон),
     * store_id, from / to (дата оформления) и sort (desc|asc по дате).
     */
    public function paginate(array $filters, int $perPage = 25): LengthAwarePaginator;

    /**
     * Сводка по заказам под теми же фильтрами: сколько заказов и покупателей,
     * и — без отказов и отмен — сколько штук продано, на какую сумму и сколько
     * из неё комиссия платформы.
     *
     * @return array{orders: int, buyers: int, qty: int, total: float, commission: float}
     */
    public function summary(array $filters): array;

    /**
     * Покупатели под теми же фильтрами: сколько заказов сделал, в каких
     * магазинах, сколько штук и на какую сумму (без отказов и отмен).
     * sort — по дате последнего заказа.
     */
    public function paginateBuyers(array $filters, int $perPage = 25): LengthAwarePaginator;

    /** Сколько заказов ждёт ответа продавца — счётчик в меню админки. */
    public function countPending(): int;

    /**
     * Магазины, у которых есть хотя бы один заказ — выпадающий фильтр в админке.
     *
     * @return \Illuminate\Support\Collection<int, array{id: int, name: string}>
     */
    public function storesWithOrders(): \Illuminate\Support\Collection;

    /**
     * Заказы магазина для владельца — с первой минуты, решение принимает он.
     * Фильтры: status (в т.ч. to_deliver / completed), q (номер / телефон / имя).
     */
    public function paginateForOwner(int $ownerId, array $filters, int $perPage = 20): LengthAwarePaginator;

    /** Заказ магазина; null — чужой или несуществующий. */
    public function findSuborderForOwner(int $suborderId, int $ownerId): ?Suborder;

    /** Перечитывает часть заказа со всеми связями — после смены статуса заказа. */
    public function refreshSuborder(Suborder $suborder): Suborder;

    /** Сколько заказов ждёт ответа владельца — бейдж в мобилке. */
    public function countPendingForOwner(int $ownerId): int;

    /**
     * Счётчики вкладок магазина: ждут ответа и приняты, но ещё не доставлены.
     *
     * @return array{pending: int, to_deliver: int}
     */
    public function tabCountsForOwner(int $ownerId): array;

    /** $deciderId — владелец магазина; при отмене покупателем решает никто. */
    public function updateStatus(Order $order, string $status, ?int $deciderId = null, ?string $comment = null): Order;

    public function updateSuborderStatus(Suborder $suborder, string $status, ?string $comment = null): Suborder;

    /**
     * Закрывает части заказа, которые ещё в перечисленных статусах: решение по
     * заказу принято, и висеть в «ждём ответа» им больше незачем.
     *
     * @param  array<int, string>  $from
     */
    public function closeSuborders(Order $order, string $status, array $from): void;

    /** Отметка «остаток по позиции списан» — по ней же остаток и возвращается. */
    public function markItemStockTaken(OrderItem $item, bool $taken): void;
}
