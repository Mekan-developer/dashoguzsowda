<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateStoreRequest;
use App\Models\Store;
use App\Models\StorePhoto;
use App\Repositories\Interfaces\CategoryRepositoryInterface;
use App\Services\StoreService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class StoreController extends Controller
{
    public function __construct(
        private readonly StoreService $storeService,
        private readonly CategoryRepositoryInterface $categories,
    ) {}

    public function index(Request $request)
    {
        return Inertia::render('Stores/Index', [
            'stores'     => $this->storeService->list($request->only('search', 'is_popular')),
            'categories' => $this->categories->activeTree(),
            'filters'    => $request->only('search', 'is_popular'),
        ]);
    }

    public function update(UpdateStoreRequest $request, Store $store)
    {
        $this->storeService->update(
            $store,
            $request->safe()->only('name', 'description', 'phone', 'address', 'category_id'),
            $request->file('logo'),
            $request->safe()->only('crop_x', 'crop_y'),
            $request->file('photos', []),
        );

        return back()->with('toast', ['type' => 'success', 'message' => __('messages.updated')]);
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
