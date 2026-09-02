<?php

namespace App\Http\Controllers\Admin;

use App\Actions\ApproveStoreAction;
use App\Actions\RejectStoreAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RejectStoreRequest;
use App\Http\Requests\Admin\UpdateStoreRequest;
use App\Models\Store;
use App\Models\StorePhoto;
use App\Repositories\Interfaces\CategoryRepositoryInterface;
use App\Repositories\Interfaces\ReasonRepositoryInterface;
use App\Repositories\Interfaces\RegionRepositoryInterface;
use App\Services\NotificationService;
use App\Services\StoreService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class StoreController extends Controller
{
    public function __construct(
        private readonly StoreService $storeService,
        private readonly CategoryRepositoryInterface $categories,
        private readonly RegionRepositoryInterface $regions,
        private readonly ReasonRepositoryInterface $reasons,
        private readonly ApproveStoreAction $approveStore,
        private readonly RejectStoreAction $rejectStore,
        private readonly NotificationService $notificationService,
    ) {}

    public function index(Request $request)
    {
        $this->notificationService->markSectionSeen($request->user(), 'stores');

        return Inertia::render('Stores/Index', [
            'stores'     => $this->storeService->list($request->only('search', 'is_popular', 'status')),
            'categories' => $this->categories->activeTree(),
            'regions'    => $this->regions->activeListWithDistricts(),
            'rejectionReasons' => $this->reasons->activeRejectionReasons('store'),
            'counts'     => $this->storeService->moderationCounts(),
            'filters'    => $request->only('search', 'is_popular', 'status'),
        ]);
    }

    public function update(UpdateStoreRequest $request, Store $store)
    {
        $this->storeService->update(
            $store,
            $request->safe()->only(
                'name', 'description', 'phone', 'address', 'category_id',
                'region_id', 'city_id', 'district_id',
                'sells_retail', 'sells_wholesale', 'has_delivery',
            ),
            $request->file('logo'),
            $request->safe()->only('crop_x', 'crop_y'),
            $request->file('photos', []),
        );

        return back()->with('toast', ['type' => 'success', 'message' => __('messages.updated')]);
    }

    /** Модерация: магазин появляется в мобильной витрине только после одобрения. */
    public function approve(Store $store)
    {
        $this->approveStore->execute($store);

        return back()->with('toast', ['type' => 'success', 'message' => __('messages.store_approved')]);
    }

    public function reject(RejectStoreRequest $request, Store $store)
    {
        $this->rejectStore->execute($store, (int) $request->validated('rejection_reason_id'));

        return back()->with('toast', ['type' => 'success', 'message' => __('messages.store_rejected')]);
    }

    public function toggle(Store $store)
    {
        $this->storeService->togglePopular($store);

        return back()->with('toast', ['type' => 'success', 'message' => __('messages.updated')]);
    }

    public function move(Request $request, Store $store)
    {
        $request->validate(['direction' => 'required|in:up,down']);
        $this->storeService->move($store, $request->input('direction'));

        return back()->with('toast', ['type' => 'success', 'message' => __('messages.updated')]);
    }

    public function destroy(Store $store)
    {
        $this->storeService->delete($store);

        return back()->with('toast', ['type' => 'success', 'message' => __('messages.deleted')]);
    }

    public function destroyPhoto(Store $store, StorePhoto $photo)
    {
        abort_unless($photo->store_id === $store->id, 404);

        $this->storeService->removePhoto($photo);

        return back()->with('toast', ['type' => 'success', 'message' => __('messages.deleted')]);
    }
}
