<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreRegionRequest;
use App\Models\Region;
use App\Repositories\Interfaces\RegionRepositoryInterface;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RegionController extends Controller
{
    public function __construct(
        private readonly RegionRepositoryInterface $regions,
    ) {}

    public function index()
    {
        return Inertia::render('Regions/Index', [
            'regions' => $this->regions->treeForAdmin(),
        ]);
    }

    public function store(StoreRegionRequest $request)
    {
        $this->regions->createRegion($request->validated());

        return back()->with('toast', ['type' => 'success', 'message' => __('messages.created')]);
    }

    public function update(StoreRegionRequest $request, Region $region)
    {
        $this->regions->updateRegion($region, $request->validated());

        return back()->with('toast', ['type' => 'success', 'message' => __('messages.updated')]);
    }

    public function toggle(Region $region)
    {
        $this->regions->setRegionHidden($region, ! $region->is_hidden);

        return back()->with('toast', ['type' => 'success', 'message' => __('messages.updated')]);
    }

    public function destroy(Request $request, Region $region)
    {
        $this->regions->deleteRegion($region);

        return back()->with('toast', ['type' => 'success', 'message' => __('messages.deleted')]);
    }
}
