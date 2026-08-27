<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\AssignTariffAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateSubscriptionRequest;
use App\Http\Resources\Api\V1\TariffResource;
use App\Http\Resources\Api\V1\TariffSummaryResource;
use App\Http\Resources\Api\V1\UserResource;
use App\Services\TariffService;
use App\Services\UserService;
use Illuminate\Http\Request;

class TariffController extends Controller
{
    public function __construct(
        private readonly TariffService $tariffService,
        private readonly UserService $userService,
        private readonly AssignTariffAction $assignTariffAction,
    ) {}

    /**
     * Текущий тариф пользователя и остаток лимитов.
     * GET /api/v1/profile/tariff
     *
     * @authenticated
     */
    public function show(Request $request)
    {
        $summary = $this->tariffService->currentForUser($request->user());

        return response()->json([
            'data' => [
                'tariff'     => $summary['tariff'] ? new TariffResource($summary['tariff']) : null,
                'expires_at' => $summary['expires_at']?->toIso8601String(),
                'remaining'  => $summary['remaining'],
            ],
            'message' => 'Success',
        ]);
    }

    /**
     * Каталог тарифных планов для выбора в профиле.
     * GET /api/v1/tariffs
     *
     * @authenticated
     */
    public function catalog()
    {
        return response()->json([
            'data' => TariffSummaryResource::collection($this->tariffService->catalogEntries()),
        ]);
    }

    /**
     * Смена тарифа. Платёжного шлюза в проекте нет — тариф назначается напрямую,
     * как и из админки (AssignTariffAction).
     * PUT /api/v1/profile/subscription
     *
     * @authenticated
     */
    public function updateSubscription(UpdateSubscriptionRequest $request)
    {
        $tariff = $this->tariffService->findBySlug($request->validated('tariff_name'));

        $this->assignTariffAction->execute($request->user(), $tariff);

        $user = $request->user()->fresh()->load('region', 'city', 'district');

        return response()->json([
            'data'    => new UserResource($user, $this->userService->profileSummary($user)),
            'message' => __('messages.tariff_assigned'),
        ]);
    }
}
