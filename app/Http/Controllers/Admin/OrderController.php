<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\NotificationService;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Заказы товаров магазинов — только просмотр.
 *
 * Заказ идёт прямо владельцу магазина: он подтверждает наличие, доставляет сам
 * и получает деньги на месте. Админ ни во что не вмешивается — раздел нужен,
 * чтобы видеть, какой товар, у кого и кому продан, в каком количестве, на какую
 * сумму и сколько из неё комиссия платформы (см. CLAUDE.md → «Заказы и корзина»).
 */
class OrderController extends Controller
{
    public function __construct(
        private readonly OrderService $orderService,
        private readonly NotificationService $notificationService,
    ) {}

    public function index(Request $request)
    {
        $this->notificationService->markSectionSeen($request->user(), 'orders');

        return Inertia::render('Orders/Index', [
            'orders'  => $this->orderService->list($request->only('status', 'search', 'store_id')),
            'stores'  => $this->orderService->storesWithOrders(),
            // «Новые» — те, по которым продавец ещё не ответил
            'counts'  => ['pending' => $this->orderService->countPending()],
            'filters' => $request->only('status', 'search', 'store_id'),
        ]);
    }
}
