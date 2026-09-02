<?php

namespace Database\Seeders;

use App\Models\Tariff;
use Illuminate\Database\Seeder;

class TariffSeeder extends Seeder
{
    public function run(): void
    {
        // price — сумма, которую пользователь передаёт админу наличными;
        // реальные цены проставляются в админке, здесь — рабочие значения для dev
        // Бесплатный тариф бессрочен: duration_days = null
        Tariff::create(['name' => 'Basic',    'name_ru' => 'Бесплатный', 'name_tk' => 'Mugt',     'price' => 0,   'listings_limit' => 5,   'videos_limit' => 2,  'boost_limit' => 3,  'duration_days' => null, 'is_free' => true,  'is_active' => true, 'can_have_store' => false]);
        Tariff::create(['name' => 'Standard', 'name_ru' => 'Стандарт',   'name_tk' => 'Standart', 'price' => 100, 'listings_limit' => 20,  'videos_limit' => 10, 'boost_limit' => 10, 'duration_days' => 30, 'is_free' => false, 'is_active' => true, 'can_have_store' => false]);
        Tariff::create(['name' => 'Premium',  'name_ru' => 'Премиум',    'name_tk' => 'Premium',  'price' => 250, 'listings_limit' => 100, 'videos_limit' => 50, 'boost_limit' => 50, 'duration_days' => 30, 'is_free' => false, 'is_active' => true, 'can_have_store' => true]);
    }
}
