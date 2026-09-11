<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\OrderIndexRequest;
use App\Services\NotificationService;
use App\Services\OrderService;
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

    public function index(OrderIndexRequest $request)
    {
        $this->notificationService->markSectionSeen($request->user(), 'orders');

        $filters = $request->validated();
        $view    = $filters['view'] ?? 'orders';

        return Inertia::render('Orders/Index', [
            // Считаем только открытую вкладку: вторая всё равно не видна
            'orders'  => $view === 'orders' ? $this->orderService->list($filters) : null,
            'buyers'  => $view === 'buyers' ? $this->orderService->listBuyers($filters) : null,
            'summary' => $this->orderService->summary($filters),
            'stores'  => $this->orderService->storesWithOrders(),
            // «Новые» — те, по которым продавец ещё не ответил
            'counts'  => ['pending' => $this->orderService->countPending()],
            'filters' => $filters + ['view' => $view, 'sort' => $filters['sort'] ?? 'desc'],
        ]);
    }
}
