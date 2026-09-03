<?php

namespace App\Repositories;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Suborder;
use App\Repositories\Interfaces\OrderRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class OrderRepository implements OrderRepositoryInterface
{
    /** Состав заказа для карточки покупателя и для админки. */
    private const FULL_RELATIONS = [
        'region', 'city', 'district',
        'suborders.store', 'suborders.user', 'suborders.items.listing.media',
    ];

    public function create(array $order, array $suborders): Order
    {
        return DB::transaction(function () use ($order, $suborders) {
            $model = Order::create($order);

            foreach ($suborders as $suborder) {
                $created = $model->suborders()->create([
                    'store_id'           => $suborder['store_id'],
                    'user_id'            => $suborder['user_id'],
                    'status'             => 'pending',
                    'subtotal'           => $suborder['subtotal'],
                    // Ставка комиссии магазина на момент заказа и её сумма
                    'commission_percent' => $suborder['commission_percent'] ?? 0,
                    'commission_total'   => $suborder['commission_total'] ?? 0,
                ]);

                foreach ($suborder['items'] as $item) {
                    $created->items()->create($item + ['order_id' => $model->id]);
                }
            }

            return $model->load(self::FULL_RELATIONS);
        });
    }

    public function findForUser(int $orderId, int $userId): ?Order
    {
        return Order::with(self::FULL_RELATIONS)
            ->where('user_id', $userId)
            ->find($orderId);
    }

    public function paginateForUser(int $userId, array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return Order::with(self::FULL_RELATIONS)
            ->where('user_id', $userId)
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function find(int $orderId): Order
    {
        return Order::with([...self::FULL_RELATIONS, 'user', 'processor'])->findOrFail($orderId);
    }

    public function paginate(array $filters, int $perPage = 25): LengthAwarePaginator
    {
        // Состав грузим сразу: админка раскрывает заказ прямо в списке,
        // отдельного запроса на карточку нет
        return Order::with(['user', 'city', 'district', 'suborders.store', 'suborders.items', 'processor'])
            ->withCount('items')
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['store_id'] ?? null, fn ($q, $id) => $q
                ->whereHas('suborders', fn ($s) => $s->where('store_id', $id)))
            ->when($filters['search'] ?? null, function ($q, $search) {
                $term = '%'.addcslashes($search, '%_\\').'%';
                // Номер заказа — это id с ведущими нулями («000123»), поэтому
                // ищем и по нему, отбросив нули и решётку
                $number = ltrim(trim($search), '#0');

                $q->where(function ($w) use ($term, $number) {
                    $w->where('phone', 'like', $term)
                        ->orWhere('contact_name', 'like', $term)
                        ->orWhereHas('user', fn ($u) => $u
                            ->where('name', 'like', $term)
                            ->orWhere('phone', 'like', $term));

                    if (is_numeric($number)) {
                        $w->orWhere('id', (int) $number);
                    }
                });
            })
            // Необработанные — наверх: админ работает именно с ними
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function countPending(): int
    {
        return Order::where('status', 'pending')->count();
    }

    public function storesWithOrders(): \Illuminate\Support\Collection
    {
        return Suborder::query()
            ->join('stores', 'stores.id', '=', 'suborders.store_id')
            ->distinct()
            ->orderBy('stores.name')
            ->pluck('stores.name', 'stores.id')
            ->map(fn ($name, $id) => ['id' => (int) $id, 'name' => $name])
            ->values();
    }

    public function paginateForOwner(int $ownerId, array $filters, int $perPage = 20): LengthAwarePaginator
    {
        // Владелец отвечает первым, поэтому видит свою часть сразу после
        // оформления заказа — включая ещё не подтверждённые админом
        return Suborder::with(['order.city', 'order.district', 'store', 'items.listing.media'])
            ->where('user_id', $ownerId)
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function findSuborderForOwner(int $suborderId, int $ownerId): ?Suborder
    {
        return Suborder::with(['order.city', 'order.district', 'store', 'items.listing.media'])
            ->where('user_id', $ownerId)
            ->find($suborderId);
    }

    public function countPendingForOwner(int $ownerId): int
    {
        return Suborder::where('user_id', $ownerId)
            ->where('status', 'pending')
            // Ждут ответа только те, чей заказ ещё не закрыт админом
            ->whereHas('order', fn ($q) => $q->where('status', 'pending'))
            ->count();
    }

    public function updateStatus(Order $order, string $status, ?int $adminId = null, ?string $comment = null): Order
    {
        $order->update([
            'status'        => $status,
            'admin_comment' => $comment ?? $order->admin_comment,
            'processed_by'  => $adminId ?? $order->processed_by,
            'processed_at'  => now(),
        ]);

        return $order->fresh([...self::FULL_RELATIONS, 'user', 'processor']);
    }

    public function updateSuborderStatus(Suborder $suborder, string $status, ?string $comment = null): Suborder
    {
        $suborder->update([
            'status'       => $status,
            'comment'      => $comment,
            'responded_at' => now(),
        ]);

        return $suborder->fresh(['order.city', 'order.district', 'store', 'items.listing.media']);
    }

    public function closeSuborders(Order $order, string $status, array $from): void
    {
        // responded_at не трогаем: это отметка об ответе владельца, а закрывает
        // часть админ — по ней потом видно, кто на самом деле отвечал
        Suborder::where('order_id', $order->id)
            ->whereIn('status', $from)
            ->update(['status' => $status]);
    }

    public function markItemStockTaken(OrderItem $item, bool $taken): void
    {
        $item->update(['stock_taken' => $taken]);
    }
}
