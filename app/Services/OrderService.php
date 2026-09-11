<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Suborder;
use App\Models\User;
use App\Repositories\Interfaces\ListingRepositoryInterface;
use App\Repositories\Interfaces\OrderRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Заказы товаров магазинов.
 *
 * Онлайн-оплаты нет и здесь: заказ уходит прямо владельцу магазина, он
 * подтверждает наличие, везёт сам и получает деньги на месте. Поэтому заказ до
 * ответа продавца ничего не резервирует — остатки списываются в момент, когда
 * он принял заказ (takeStock). Возврат (releaseStock) идёт строго по позициям
 * с stock_taken, поэтому отказ и отмена до ответа продавца ничего не трогают.
 */
class OrderService
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly ListingRepositoryInterface $listingRepository,
    ) {}

    /**
     * @param  array<string, mixed>  $order
     * @param  array<int, array<string, mixed>>  $suborders  позиции магазина; в заказе он один
     */
    public function create(array $order, array $suborders): Order
    {
        return $this->orderRepository->create($order, $suborders);
    }

    public function findForUser(int $orderId, User $user): ?Order
    {
        return $this->orderRepository->findForUser($orderId, $user->id);
    }

    public function listForUser(User $user, array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return $this->orderRepository->paginateForUser($user->id, $filters, $perPage);
    }

    public function find(int $orderId): Order
    {
        return $this->orderRepository->find($orderId);
    }

    public function list(array $filters): LengthAwarePaginator
    {
        return $this->orderRepository->paginate($filters);
    }

    /** @return array{orders: int, buyers: int, qty: int, total: float, commission: float} */
    public function summary(array $filters): array
    {
        return $this->orderRepository->summary($filters);
    }

    public function listBuyers(array $filters): LengthAwarePaginator
    {
        return $this->orderRepository->paginateBuyers($filters);
    }

    public function countPending(): int
    {
        return $this->orderRepository->countPending();
    }

    /** @return \Illuminate\Support\Collection<int, array{id: int, name: string}> */
    public function storesWithOrders(): \Illuminate\Support\Collection
    {
        return $this->orderRepository->storesWithOrders();
    }

    public function listForOwner(User $owner, array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return $this->orderRepository->paginateForOwner($owner->id, $filters, $perPage);
    }

    public function findSuborderForOwner(int $suborderId, User $owner): ?Suborder
    {
        return $this->orderRepository->findSuborderForOwner($suborderId, $owner->id);
    }

    /** Перечитывает часть заказа — после того, как сменился статус заказа. */
    public function refreshSuborder(Suborder $suborder): Suborder
    {
        return $this->orderRepository->refreshSuborder($suborder);
    }

    public function countPendingForOwner(User $owner): int
    {
        return $this->orderRepository->countPendingForOwner($owner->id);
    }

    /** $decider — владелец магазина; при отмене покупателем решает никто. */
    public function changeStatus(Order $order, string $status, ?User $decider = null, ?string $comment = null): Order
    {
        return $this->orderRepository->updateStatus($order, $status, $decider?->id, $comment);
    }

    public function respondToSuborder(Suborder $suborder, string $status, ?string $comment = null): Suborder
    {
        return $this->orderRepository->updateSuborderStatus($suborder, $status, $comment);
    }

    /**
     * Закрывает часть магазина, оставшуюся без ответа: покупатель отменил
     * заказ раньше, чем продавец ответил. Иначе она висела бы в «ждём ответа».
     *
     * @param  array<int, string>  $from
     */
    public function closeSuborders(Order $order, string $status, array $from = ['pending']): void
    {
        $this->orderRepository->closeSuborders($order, $status, $from);
    }

    /**
     * Списывает остатки по позициям заказа. Вызывается, когда продавец принял
     * заказ: до этого заказ — только заявка, держать под неё товар незачем.
     *
     * Часть магазина со статусом declined пропускается: её товар никуда не
     * едет, и списывать его нельзя.
     */
    public function takeStock(Order $order): void
    {
        $order->loadMissing('suborders.items');

        foreach ($order->suborders as $suborder) {
            if ($suborder->status === 'declined') {
                continue;
            }

            foreach ($suborder->items->where('stock_taken', false) as $item) {
                if (! $item->listing_id) {
                    continue;
                }

                $this->listingRepository->adjustStock($item->listing_id, -$item->qty);
                $this->orderRepository->markItemStockTaken($item, true);
            }
        }
    }

    /**
     * Возвращает остатки — при отмене заказа. Возвращаются только позиции с
     * stock_taken: у товара без учёта остатков списания не было, и «возврат»
     * задрал бы ему количество.
     */
    public function releaseStock(Order|Suborder $scope): void
    {
        $items = $scope->items()->where('stock_taken', true)->get();

        foreach ($items as $item) {
            if ($item->listing_id) {
                $this->listingRepository->adjustStock($item->listing_id, $item->qty);
            }

            $this->orderRepository->markItemStockTaken($item, false);
        }
    }
}
