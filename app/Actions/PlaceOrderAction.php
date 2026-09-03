<?php

namespace App\Actions;

use App\Events\OrderPlaced;
use App\Models\Listing;
use App\Models\Order;
use App\Models\User;
use App\Repositories\Interfaces\ListingRepositoryInterface;
use App\Services\OrderService;
use Illuminate\Validation\ValidationException;

/**
 * POST /v1/orders — оформление заказа из корзины.
 *
 * Корзина живёт на устройстве, поэтому её содержимое приходит сюда «сырым» и
 * проверяется целиком заново: пока покупатель набирал товары, цена могла
 * измениться, товар — уехать на повторную модерацию, а магазин — выключить
 * доставку. Цена, по которой заказ подтверждён, фиксируется в позициях.
 *
 * Товары из разных магазинов складываются в один заказ, но разбиваются на
 * подзаказы: админ ведёт заказ целиком, а каждый владелец видит только свою
 * часть (см. CLAUDE.md → «Заказы»).
 */
class PlaceOrderAction
{
    /** Разных товаров в одном заказе. Ограничение от абузов, не от бизнеса. */
    public const MAX_ITEMS = 50;

    public function __construct(
        private readonly OrderService $orderService,
        private readonly ListingRepositoryInterface $listingRepository,
    ) {}

    /**
     * @param  array{items: array<int, array{listing_id: int|string, qty: int|string}>, phone?: string|null, contact_name?: string|null, region_id?: int|null, city_id?: int|null, district_id?: int|null, address: string, comment?: string|null}  $data
     */
    public function execute(User $buyer, array $data): Order
    {
        $quantities = $this->mergeQuantities($data['items'] ?? []);

        if (count($quantities) > self::MAX_ITEMS) {
            throw ValidationException::withMessages([
                'items' => __('messages.order_items_limit', ['limit' => self::MAX_ITEMS]),
            ]);
        }

        $listings = $this->listingRepository
            ->findManyWithStore(array_keys($quantities))
            ->keyBy('id');

        $suborders = [];
        $total = 0.0;

        foreach ($quantities as $listingId => $qty) {
            /** @var Listing|null $listing */
            $listing = $listings->get($listingId);

            $this->assertOrderable($listing, $buyer, $qty);

            [$unitPrice, $isWholesale] = $this->resolvePrice($listing, $qty);
            $lineTotal = round($unitPrice * $qty, 2);
            $total += $lineTotal;

            $store = $listing->store;

            $suborders[$store->id] ??= [
                'store_id' => $store->id,
                'user_id'  => $store->user_id,
                'subtotal' => 0.0,
                'items'    => [],
            ];

            $suborders[$store->id]['items'][] = [
                'listing_id'   => $listing->id,
                'title'        => $listing->title,
                'unit_price'   => $unitPrice,
                'is_wholesale' => $isWholesale,
                'qty'          => $qty,
                'total'        => $lineTotal,
            ];
            $suborders[$store->id]['subtotal'] += $lineTotal;
        }

        $order = $this->orderService->create([
            'user_id'      => $buyer->id,
            'status'       => 'pending',
            'total'        => round($total, 2),
            'contact_name' => $data['contact_name'] ?? $buyer->name,
            'phone'        => $data['phone'] ?? $buyer->phone,
            'region_id'    => $data['region_id']   ?? $buyer->region_id,
            'city_id'      => $data['city_id']     ?? $buyer->city_id,
            'district_id'  => $data['district_id'] ?? $buyer->district_id,
            'address'      => $data['address'],
            'comment'      => $data['comment'] ?? null,
        ], array_values($suborders));

        // Владельцы магазинов узнают о заказе сразу: подтверждают наличие они,
        // а админ подтверждает заказ уже по их ответам
        event(new OrderPlaced($order));

        return $order;
    }

    /**
     * Один и тот же товар мог попасть в корзину дважды — складываем количество,
     * иначе проверка остатка пропустит два заказа по половине склада.
     *
     * @param  array<int, array{listing_id: int|string, qty: int|string}>  $items
     * @return array<int, int>
     */
    private function mergeQuantities(array $items): array
    {
        $quantities = [];

        foreach ($items as $row) {
            $id  = (int) ($row['listing_id'] ?? 0);
            $qty = (int) ($row['qty'] ?? 0);

            if ($id > 0 && $qty > 0) {
                $quantities[$id] = ($quantities[$id] ?? 0) + $qty;
            }
        }

        if (empty($quantities)) {
            throw ValidationException::withMessages([
                'items' => __('messages.order_items_required'),
            ]);
        }

        return $quantities;
    }

    /** Причины отказа разделены: покупателю важно понять, что чинить в корзине. */
    private function assertOrderable(?Listing $listing, User $buyer, int $qty): void
    {
        if (! $listing) {
            throw ValidationException::withMessages([
                'items' => __('messages.order_item_missing'),
            ]);
        }

        $store = $listing->store;

        if ($listing->status !== 'approved' || ! $store || ! $store->isPublic()) {
            throw ValidationException::withMessages([
                'items' => __('messages.order_item_unavailable', ['title' => $listing->title]),
            ]);
        }

        if ($listing->user_id === $buyer->id) {
            throw ValidationException::withMessages([
                'items' => __('messages.order_item_own'),
            ]);
        }

        if (! $store->has_delivery) {
            throw ValidationException::withMessages([
                'items' => __('messages.order_store_no_delivery', ['name' => $store->name]),
            ]);
        }

        if ($listing->stock_qty !== null) {
            if ($listing->stock_qty < 1) {
                throw ValidationException::withMessages([
                    'items' => __('messages.order_item_out_of_stock', ['title' => $listing->title]),
                ]);
            }

            if ($qty > $listing->stock_qty) {
                throw ValidationException::withMessages([
                    'items' => __('messages.order_item_stock_exceeded', [
                        'title' => $listing->title,
                        'count' => $listing->stock_qty,
                    ]),
                ]);
            }
        }
    }

    /**
     * Опт и розница — независимые ценники: оптовый применяется сам, как только
     * количество дотянуло до min_order_qty. Товар только с оптовой ценой в
     * розницу не продаётся — заказ на меньшее количество отбивается.
     *
     * @return array{0: float, 1: bool} цена за единицу и признак оптовой
     */
    private function resolvePrice(Listing $listing, int $qty): array
    {
        $minQty = $listing->min_order_qty;

        if ($listing->wholesale_price !== null && ($minQty === null || $qty >= $minQty)) {
            return [(float) $listing->wholesale_price, true];
        }

        if ($listing->price !== null) {
            return [(float) $listing->price, false];
        }

        if ($listing->wholesale_price !== null) {
            throw ValidationException::withMessages([
                'items' => __('messages.order_item_min_qty', [
                    'title' => $listing->title,
                    'count' => $minQty,
                ]),
            ]);
        }

        throw ValidationException::withMessages([
            'items' => __('messages.order_item_no_price', ['title' => $listing->title]),
        ]);
    }
}
