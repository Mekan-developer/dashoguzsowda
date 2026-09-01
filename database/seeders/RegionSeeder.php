<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Region;
use Illuminate\Database\Seeder;

class RegionSeeder extends Seeder
{
    public function run(): void
    {
        // 'cities' может быть строкой (город без районов) либо массивом [город => [районы...]].
        $regionData = [
            ['name_ru' => 'Ахалский',   'name_tk' => 'Ahal',    'cities' => ['Ашхабад','Аннау', 'Бахерден', 'Геок-Тепе', 'Теджен']],
            ['name_ru' => 'Балканский', 'name_tk' => 'Balkan',  'cities' => ['Балканабат', 'Туркменбаши', 'Сердар']],
            ['name_ru' => 'Дашогузский', 'name_tk' => 'Daşoguz', 'cities' => ['Дашогуз', 'Болдумсаз', 'Куняургенч']],
            ['name_ru' => 'Лебапский',  'name_tk' => 'Lebap',   'cities' => ['Туркменабад', 'Фараб', 'Сейди']],
            ['name_ru' => 'Марыйский',  'name_tk' => 'Mary',    'cities' => [
                'Мары',
                'Байрамали',
                'Ёлётен' => ['Варашыл', 'Тагтабазар'],
            ]],
        ];

        foreach ($regionData as $rd) {
            $region = Region::create(['name_ru' => $rd['name_ru'], 'name_tk' => $rd['name_tk']]);

            foreach ($rd['cities'] as $key => $value) {
                $cityName  = is_array($value) ? $key : $value;
                $districts = is_array($value) ? $value : [];

                $city = City::create(['region_id' => $region->id, 'name_ru' => $cityName, 'name_tk' => $cityName]);

                foreach ($districts as $district) {
                    $city->districts()->create(['name_ru' => $district, 'name_tk' => $district]);
                }
            }
        }
    }
}
