<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\CheckStoreTariffAction;
use App\Actions\UpdateUserStoreAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreOwnStoreRequest;
use App\Http\Requests\Api\V1\UpdateOwnStoreRequest;
use App\Http\Resources\Api\V1\MyStoreResource;
use App\Models\StorePhoto;
use App\Services\StoreService;
use Illuminate\Http\Request;

/**
 * Свой магазин. Один магазин на пользователя, поэтому маршруты без {id}.
 * Доступен только на тарифе с can_have_store (CheckStoreTariffAction).
 */
class MyStoreController extends Controller
{
    public function __construct(
        private readonly StoreService $storeService,
        private readonly UpdateUserStoreAction $updateUserStore,
        private readonly CheckStoreTariffAction $checkStoreTariff,
    ) {}

    /**
     * GET /api/v1/my/store — 404, если магазина ещё нет.
     *
     * @authenticated
     */
    public function show(Request $request)
    {
        $store = $this->storeService->findByUser($request->user());

        abort_unless((bool) $store, 404, __('messages.store_not_found'));

        return response()->json([
            'data'    => new MyStoreResource($store),
            'message' => 'Success',
        ]);
    }

    /**
     * POST /api/v1/my/store — создание. Магазин уходит на модерацию.
     *
     * @authenticated
     */
    public function store(StoreOwnStoreRequest $request)
    {
        $this->checkStoreTariff->execute($request->user());

        abort_if(
            (bool) $this->storeService->findByUser($request->user()),
            422,
            __('messages.store_already_exists'),
        );

        $store = $this->updateUserStore->execute(
            $request->user(),
            $request->safe()->except('logo', 'photos', 'crop_x', 'crop_y'),
            $request->file('logo'),
            $request->safe()->only('crop_x', 'crop_y'),
            $request->file('photos', []),
        );

        return response()->json([
            'data'    => new MyStoreResource($store),
            'message' => __('messages.store_sent_to_moderation'),
        ], 201);
    }

    /**
     * PUT /api/v1/my/store — правка. Изменение названия, описания, адреса или
     * логотипа возвращает магазин на модерацию (StoreService::MODERATED_FIELDS).
     *
     * @authenticated
     */
    public function update(UpdateOwnStoreRequest $request)
    {
        $this->checkStoreTariff->execute($request->user());

        abort_unless(
            (bool) $this->storeService->findByUser($request->user()),
            404,
            __('messages.store_not_found'),
        );

        $store = $this->updateUserStore->execute(
            $request->user(),
            $request->safe()->except('logo', 'photos', 'crop_x', 'crop_y'),
            $request->file('logo'),
            $request->safe()->only('crop_x', 'crop_y'),
            $request->file('photos', []),
        );

        return response()->json([
            'data'    => new MyStoreResource($store),
            'message' => $store->status === 'pending'
                ? __('messages.store_sent_to_moderation')
                : __('messages.updated'),
        ]);
    }

    /**
     * DELETE /api/v1/my/store/photos/{photo} — удаление одного фото галереи.
     *
     * @authenticated
     */
    public function destroyPhoto(Request $request, StorePhoto $photo)
    {
        $store = $this->storeService->findByUser($request->user());

        abort_unless($store && $photo->store_id === $store->id, 404);

        $this->storeService->removePhoto($photo);

        return response()->json([
            'message' => __('messages.deleted'),
        ]);
    }
}
