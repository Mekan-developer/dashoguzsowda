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
 * Онлайн-оплаты нет и здесь: покупатель оформляет заказ, админ обзванивает
 * магазины и подтверждает наличие, деньги передаются при доставке. Поэтому
 * заказ до подтверждения ничего не резервирует — остатки списываются только
 * в момент approve (takeStock) и возвращаются при отказе или отмене.
 */
class OrderService
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly ListingRepositoryInterface $listingRepository,
    ) {}

    /**
     * @param  array<string, mixed>  $order
     * @param  array<int, array<string, mixed>>  $suborders  сгруппированные по магазину позиции
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

    public function countPendingForOwner(User $owner): int
    {
        return $this->orderRepository->countPendingForOwner($owner->id);
    }

    public function changeStatus(Order $order, string $status, ?User $admin = null, ?string $comment = null): Order
    {
        return $this->orderRepository->updateStatus($order, $status, $admin?->id, $comment);
    }

    public function respondToSuborder(Suborder $suborder, string $status, ?string $comment = null): Suborder
    {
        return $this->orderRepository->updateSuborderStatus($suborder, $status, $comment);
    }

    /**
     * Закрывает части, оставшиеся без ответа, когда решение по заказу принято.
     * Иначе они висят в «ждём ответа» даже у доставленного заказа.
     *
     * @param  array<int, string>  $from
     */
    public function closeSuborders(Order $order, string $status, array $from = ['pending']): void
    {
        $this->orderRepository->closeSuborders($order, $status, $from);
    }

    /**
     * Списывает остатки по позициям заказа. Вызывается при подтверждении
     * админом: до него заказ — только заявка, и держать под неё товар незачем.
     *
     * Части магазинов, которые к этому моменту отказались, пропускаются: их
     * товар никуда не едет, и списывать его нельзя.
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
     * Возвращает остатки — при отказе владельца, отмене или отклонении заказа.
     * Возвращаются только позиции с stock_taken: у товара без учёта остатков
     * списания не было, и «возврат» задрал бы ему количество.
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
