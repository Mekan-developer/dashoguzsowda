<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCityRequest;
use App\Models\City;
use App\Repositories\Interfaces\RegionRepositoryInterface;
use Illuminate\Http\Request;

class CityController extends Controller
{
    public function __construct(
        private readonly RegionRepositoryInterface $regions,
    ) {}

    public function store(StoreCityRequest $request)
    {
        $this->regions->createCity($request->validated());

        return back()->with('toast', ['type' => 'success', 'message' => __('messages.created')]);
    }

    public function update(StoreCityRequest $request, City $city)
    {
        $this->regions->updateCity($city, $request->validated());

        return back()->with('toast', ['type' => 'success', 'message' => __('messages.updated')]);
    }

    public function toggle(City $city)
    {
        $this->regions->setCityHidden($city, ! $city->is_hidden);

        return back()->with('toast', ['type' => 'success', 'message' => __('messages.updated')]);
    }

    public function destroy(Request $request, City $city)
    {
        $this->regions->deleteCity($city);

        return back()->with('toast', ['type' => 'success', 'message' => __('messages.deleted')]);
    }
}
