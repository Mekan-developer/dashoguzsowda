<?php

namespace App\Repositories;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Suborder;
use App\Repositories\Interfaces\OrderRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class OrderRepository implements OrderRepositoryInterface
{
    /** Состав заказа для карточки покупателя и для админки. */
    private const FULL_RELATIONS = [
        'region', 'city', 'district', 'paymentMethod',
        'suborders.store', 'suborders.user', 'suborders.items.listing.media',
    ];

    /**
     * Заказ глазами продавца. Адрес и город грузятся вместе с ним: доставку
     * делает он сам, значит и адрес доставки — его рабочие данные.
     */
    private const OWNER_RELATIONS = [
        'order.region', 'order.city', 'order.district', 'order.user', 'order.paymentMethod',
        'store', 'items.listing.media',
    ];

    /**
     * Отказ продавца и отмена покупателем денег не приносят: такие заказы в
     * списке видны, но в штуки и суммы сводки и покупателей не входят.
     */
    private const UNPAID_STATUSES = ['rejected', 'canceled'];

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
        return Order::with([...self::FULL_RELATIONS, 'user', 'decider'])->findOrFail($orderId);
    }

    public function paginate(array $filters, int $perPage = 25): LengthAwarePaginator
    {
        $direction = self::direction($filters);

        // Состав грузим сразу: админка раскрывает заказ прямо в списке,
        // отдельного запроса на карточку нет. Владелец магазина — рядом с
        // магазином: админ смотрит, кто именно продал и кому
        return $this->filtered($filters)
            ->with(['user', 'city', 'district', 'paymentMethod', 'suborders.store', 'suborders.user', 'suborders.items', 'decider'])
            ->withCount('items')
            // Сколько штук в заказе — позиций может быть одна, а мешков десять
            ->withSum('items as items_qty', 'qty')
            // Админ заказы не ведёт, поэтому «ждущие» наверх не поднимаются:
            // список идёт строго по дате, как он её выбрал
            ->orderBy('orders.created_at', $direction)
            ->orderBy('orders.id', $direction)
            ->paginate($perPage)
            ->withQueryString();
    }

    public function summary(array $filters): array
    {
        $paid = $this->filtered($filters)->whereNotIn('orders.status', self::UNPAID_STATUSES);

        $money = (clone $paid)
            ->selectRaw('COALESCE(SUM(total), 0) as total, COALESCE(SUM(commission_total), 0) as commission')
            ->first();

        return [
            'orders'     => $this->filtered($filters)->count(),
            'buyers'     => $this->filtered($filters)->distinct()->count('orders.user_id'),
            'qty'        => (int) OrderItem::whereIn('order_id', (clone $paid)->select('orders.id'))->sum('qty'),
            'total'      => round((float) $money->total, 2),
            'commission' => round((float) $money->commission, 2),
        ];
    }

    public function paginateBuyers(array $filters, int $perPage = 25): LengthAwarePaginator
    {
        $direction = self::direction($filters);
        $unpaid    = "'".implode("','", self::UNPAID_STATUSES)."'";

        // Штуки по каждому заказу считаем заранее: прямой join с позициями
        // размножил бы строки заказов и задрал их число и суммы
        $qty = OrderItem::query()
            ->selectRaw('order_id, SUM(qty) as qty')
            ->groupBy('order_id');

        $buyers = $this->filtered($filters)
            ->leftJoinSub($qty, 'order_qty', 'order_qty.order_id', '=', 'orders.id')
            ->select('orders.user_id')
            ->selectRaw('COUNT(*) as orders_count')
            ->selectRaw("SUM(CASE WHEN orders.status IN ($unpaid) THEN 0 ELSE COALESCE(order_qty.qty, 0) END) as qty")
            ->selectRaw("SUM(CASE WHEN orders.status IN ($unpaid) THEN 0 ELSE orders.total END) as total")
            ->selectRaw('MAX(orders.created_at) as last_order_at')
            ->with('user:id,name,phone')
            ->groupBy('orders.user_id')
            ->orderBy('last_order_at', $direction)
            ->orderBy('orders.user_id', $direction)
            ->paginate($perPage)
            ->withQueryString();

        // В каких магазинах и сколько раз заказывал — отдельным запросом по
        // покупателям этой страницы, с теми же фильтрами
        $stores = $this->filtered($filters)
            ->whereIn('orders.user_id', $buyers->pluck('user_id'))
            ->join('suborders', 'suborders.order_id', '=', 'orders.id')
            ->leftJoin('stores', 'stores.id', '=', 'suborders.store_id')
            ->select('orders.user_id', 'suborders.store_id', 'stores.name')
            ->selectRaw('COUNT(*) as orders_count')
            ->groupBy('orders.user_id', 'suborders.store_id', 'stores.name')
            ->orderByDesc('orders_count')
            ->toBase()
            ->get()
            ->groupBy('user_id');

        return $buyers->through(fn (Order $row) => [
            'id'            => $row->user_id,
            'name'          => $row->user?->name,
            'phone'         => $row->user?->phone,
            'orders_count'  => (int) $row->orders_count,
            'qty'           => (int) $row->qty,
            'total'         => round((float) $row->total, 2),
            'last_order_at' => $row->last_order_at
                ? Carbon::parse($row->last_order_at)->toJSON()
                : null,
            'stores'        => ($stores[$row->user_id] ?? collect())
                ->map(fn ($s) => [
                    'id'           => $s->store_id ? (int) $s->store_id : null,
                    'name'         => $s->name,
                    'orders_count' => (int) $s->orders_count,
                ])
                ->values(),
        ]);
    }

    private static function direction(array $filters): string
    {
        return ($filters['sort'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
    }

    /** Фильтры админки — общие для списка, сводки и покупателей. */
    private function filtered(array $filters): Builder
    {
        return Order::query()
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('orders.status', $status))
            ->when($filters['store_id'] ?? null, fn ($q, $id) => $q
                ->whereHas('suborders', fn ($s) => $s->where('store_id', $id)))
            ->when($filters['from'] ?? null, fn ($q, $from) => $q->whereDate('orders.created_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($q, $to) => $q->whereDate('orders.created_at', '<=', $to))
            ->when($filters['search'] ?? null, function ($q, $search) {
                $term = '%'.addcslashes($search, '%_\\').'%';
                // Номер заказа — это id с ведущими нулями («000123»), поэтому
                // ищем и по нему, отбросив нули и решётку
                $number = ltrim(trim($search), '#0');

                $q->where(function ($w) use ($term, $number) {
                    $w->where('orders.phone', 'like', $term)
                        ->orWhere('orders.contact_name', 'like', $term)
                        ->orWhereHas('user', fn ($u) => $u
                            ->where('name', 'like', $term)
                            ->orWhere('phone', 'like', $term));

                    if (is_numeric($number)) {
                        $w->orWhere('orders.id', (int) $number);
                    }
                });
            });
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
        // Заказ приходит владельцу сразу после оформления: решение по нему
        // принимает он, поэтому видит его с первой минуты
        return Suborder::with(self::OWNER_RELATIONS)
            ->where('user_id', $ownerId)
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function findSuborderForOwner(int $suborderId, int $ownerId): ?Suborder
    {
        return Suborder::with(self::OWNER_RELATIONS)
            ->where('user_id', $ownerId)
            ->find($suborderId);
    }

    public function refreshSuborder(Suborder $suborder): Suborder
    {
        return $suborder->fresh(self::OWNER_RELATIONS);
    }

    public function countPendingForOwner(int $ownerId): int
    {
        return Suborder::where('user_id', $ownerId)
            ->where('status', 'pending')
            // Ждут ответа только те, чей заказ ещё не закрыт: покупатель мог
            // отменить его раньше, чем продавец успел ответить
            ->whereHas('order', fn ($q) => $q->where('status', 'pending'))
            ->count();
    }

    public function updateStatus(Order $order, string $status, ?int $deciderId = null, ?string $comment = null): Order
    {
        $order->update([
            'status'           => $status,
            'decision_comment' => $comment ?? $order->decision_comment,
            'decided_by'       => $deciderId ?? $order->decided_by,
            'decided_at'       => now(),
        ]);

        return $order->fresh([...self::FULL_RELATIONS, 'user', 'decider']);
    }

    public function updateSuborderStatus(Suborder $suborder, string $status, ?string $comment = null): Suborder
    {
        $suborder->update([
            'status'       => $status,
            'comment'      => $comment,
            'responded_at' => now(),
        ]);

        return $suborder->fresh(self::OWNER_RELATIONS);
    }

    public function closeSuborders(Order $order, string $status, array $from): void
    {
        // responded_at не трогаем: это отметка об ответе владельца, а закрывает
        // часть отмена покупателя — по ней потом видно, отвечал ли продавец
        Suborder::where('order_id', $order->id)
            ->whereIn('status', $from)
            ->update(['status' => $status]);
    }

    public function markItemStockTaken(OrderItem $item, bool $taken): void
    {
        $item->update(['stock_taken' => $taken]);
    }
}
