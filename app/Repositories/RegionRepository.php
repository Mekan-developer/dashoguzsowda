<?php

namespace App\Repositories;

use App\Models\City;
use App\Models\District;
use App\Models\Region;
use App\Repositories\Interfaces\RegionRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class RegionRepository implements RegionRepositoryInterface
{
    public function all(): Collection
    {
        return Region::orderBy('name_ru')->get();
    }

    public function activeList(): Collection
    {
        return Region::with(['cities' => fn ($q) => $q->where('is_hidden', false)->orderBy('name_ru')])
            ->where('is_hidden', false)
            ->orderBy('name_ru')
            ->get();
    }

    public function activeListWithDistricts(): Collection
    {
        return Region::with([
                'cities' => fn ($q) => $q->where('is_hidden', false)->orderBy('name_ru'),
                'cities.districts' => fn ($q) => $q->where('is_hidden', false)->orderBy('name_ru'),
            ])
            ->where('is_hidden', false)
            ->orderBy('name_ru')
            ->get();
    }

    public function treeForAdmin(): Collection
    {
        return Region::with([
                'cities' => fn ($q) => $q->orderBy('name_ru')->with([
                    'districts' => fn ($q) => $q->orderBy('name_ru'),
                ]),
            ])
            ->orderBy('name_ru')
            ->get();
    }

    // ─── Регионы ────────────────────────────────────────────────────────────

    public function createRegion(array $data): Region
    {
        return Region::create($data);
    }

    public function updateRegion(Region $region, array $data): Region
    {
        $region->update($data);

        return $region->fresh();
    }

    public function deleteRegion(Region $region): void
    {
        $region->delete();
    }

    /**
     * Скрытие региона каскадно прячет города и районы.
     * Обратное раскрытие каскада НЕ делает: какие именно города были скрыты
     * до этого, неизвестно — админ включает их точечно.
     */
    public function setRegionHidden(Region $region, bool $hidden): void
    {
        $region->update(['is_hidden' => $hidden]);

        if (! $hidden) {
            return;
        }

        $cityIds = $region->cities()->pluck('id');

        $region->cities()->update(['is_hidden' => true]);
        District::whereIn('city_id', $cityIds)->update(['is_hidden' => true]);
    }

    // ─── Города ─────────────────────────────────────────────────────────────

    public function createCity(array $data): City
    {
        return City::create($data);
    }

    public function updateCity(City $city, array $data): City
    {
        $city->update($data);

        return $city->fresh();
    }

    public function deleteCity(City $city): void
    {
        $city->delete();
    }

    public function setCityHidden(City $city, bool $hidden): void
    {
        $city->update(['is_hidden' => $hidden]);

        if ($hidden) {
            $city->districts()->update(['is_hidden' => true]);
        }
    }

    // ─── Районы ─────────────────────────────────────────────────────────────

    public function createDistrict(City $city, array $data): District
    {
        return $city->districts()->create($data);
    }

    public function updateDistrict(District $district, array $data): District
    {
        $district->update($data);

        return $district->fresh();
    }

    public function deleteDistrict(District $district): void
    {
        $district->delete();
    }

    public function setDistrictHidden(District $district, bool $hidden): void
    {
        $district->update(['is_hidden' => $hidden]);
    }
}
