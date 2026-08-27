<?php

namespace Database\Seeders;

use App\Models\Tariff;
use Illuminate\Database\Seeder;

class TariffSeeder extends Seeder
{
    public function run(): void
    {
        Tariff::create(['name' => 'Basic',    'name_ru' => 'Бесплатный', 'name_tk' => 'Mugt',     'listings_limit' => 5,   'videos_limit' => 2,  'boost_limit' => 3,  'duration_days' => 30, 'is_free' => true,  'is_active' => true, 'can_have_store' => false]);
        Tariff::create(['name' => 'Standard', 'name_ru' => 'Стандарт',   'name_tk' => 'Standart', 'listings_limit' => 20,  'videos_limit' => 10, 'boost_limit' => 10, 'duration_days' => 30, 'is_free' => false, 'is_active' => true, 'can_have_store' => false]);
        Tariff::create(['name' => 'Premium',  'name_ru' => 'Премиум',    'name_tk' => 'Premium',  'listings_limit' => 100, 'videos_limit' => 50, 'boost_limit' => 50, 'duration_days' => 30, 'is_free' => false, 'is_active' => true, 'can_have_store' => true]);
    }
}
