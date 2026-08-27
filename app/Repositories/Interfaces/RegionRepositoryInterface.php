<?php

namespace App\Repositories\Interfaces;

use App\Models\City;
use App\Models\District;
use App\Models\Region;
use Illuminate\Database\Eloquent\Collection;

interface RegionRepositoryInterface
{
    /** Все регионы, включая скрытые — для фильтров в админке. */
    public function all(): Collection;
    public function activeList(): Collection;
    public function activeListWithDistricts(): Collection;

    /** Полное дерево регион → город → район для страницы справочника. */
    public function treeForAdmin(): Collection;

    public function createRegion(array $data): Region;
    public function updateRegion(Region $region, array $data): Region;
    public function deleteRegion(Region $region): void;
    /** Скрытие региона каскадно прячет города и районы. */
    public function setRegionHidden(Region $region, bool $hidden): void;

    public function createCity(array $data): City;
    public function updateCity(City $city, array $data): City;
    public function deleteCity(City $city): void;
    public function setCityHidden(City $city, bool $hidden): void;

    public function createDistrict(City $city, array $data): District;
    public function updateDistrict(District $district, array $data): District;
    public function deleteDistrict(District $district): void;
    public function setDistrictHidden(District $district, bool $hidden): void;
}
