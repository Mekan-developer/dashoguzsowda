<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreDistrictRequest;
use App\Models\City;
use App\Models\District;
use App\Repositories\Interfaces\RegionRepositoryInterface;
use Illuminate\Http\Request;

class DistrictController extends Controller
{
    public function __construct(
        private readonly RegionRepositoryInterface $regions,
    ) {}

    public function store(StoreDistrictRequest $request, City $city)
    {
        $this->regions->createDistrict($city, $request->validated());

        return back()->with('toast', ['type' => 'success', 'message' => __('messages.created')]);
    }

    public function update(StoreDistrictRequest $request, District $district)
    {
        $this->regions->updateDistrict($district, $request->validated());

        return back()->with('toast', ['type' => 'success', 'message' => __('messages.updated')]);
    }

    public function toggle(District $district)
    {
        $this->regions->setDistrictHidden($district, ! $district->is_hidden);

        return back()->with('toast', ['type' => 'success', 'message' => __('messages.updated')]);
    }

    public function destroy(Request $request, District $district)
    {
        $this->regions->deleteDistrict($district);

        return back()->with('toast', ['type' => 'success', 'message' => __('messages.deleted')]);
    }
}
