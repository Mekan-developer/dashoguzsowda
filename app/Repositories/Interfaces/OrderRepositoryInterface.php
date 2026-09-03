<?php

namespace App\Repositories\Interfaces;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Suborder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface OrderRepositoryInterface
{
    /**
     * Создаёт заказ целиком: сам заказ, подзаказы по магазинам и позиции.
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

    /** Очередь заказов в админке: фильтры status, search (номер / имя / телефон), store_id. */
    public function paginate(array $filters, int $perPage = 25): LengthAwarePaginator;

    /** Сколько заказов ждёт обработки — счётчик в меню админки. */
    public function countPending(): int;

    /**
     * Магазины, у которых есть хотя бы один заказ — выпадающий фильтр в админке.
     *
     * @return \Illuminate\Support\Collection<int, array{id: int, name: string}>
     */
    public function storesWithOrders(): \Illuminate\Support\Collection;

    /** Подзаказы магазина для владельца: только по подтверждённым заказам. */
    public function paginateForOwner(int $ownerId, array $filters, int $perPage = 20): LengthAwarePaginator;

    /** Подзаказ владельца; null — чужой, несуществующий или ещё не подтверждённый админом. */
    public function findSuborderForOwner(int $suborderId, int $ownerId): ?Suborder;

    /** Сколько подзаказов ждёт ответа владельца — бейдж в мобилке. */
    public function countPendingForOwner(int $ownerId): int;

    public function updateStatus(Order $order, string $status, ?int $adminId = null, ?string $comment = null): Order;

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
