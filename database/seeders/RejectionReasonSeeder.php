<?php

namespace Database\Seeders;

use App\Models\RejectionReason;
use Illuminate\Database\Seeder;

class RejectionReasonSeeder extends Seeder
{
    public function run(): void
    {
        $rejections = [
            ['name_ru' => 'Неприемлемый контент',     'name_tk' => 'Kabul edilmeýän mazmun',  'type' => 'listing'],
            ['name_ru' => 'Неверная категория',        'name_tk' => 'Nädogry kategoriýa',       'type' => 'listing'],
            ['name_ru' => 'Дублирующее объявление',    'name_tk' => 'Gaýtalanýan bildiriş',     'type' => 'listing'],
            ['name_ru' => 'Недостаточно фото',         'name_tk' => 'Surat ýeterlik däl',       'type' => 'listing'],
            ['name_ru' => 'Запрещённый товар',         'name_tk' => 'Gadagan edilen haryt',     'type' => 'listing'],
            ['name_ru' => 'Неприемлемое видео',        'name_tk' => 'Kabul edilmeýän wideo',    'type' => 'video'],
            ['name_ru' => 'Оскорбительный отзыв',      'name_tk' => 'Kemsidiji syn',            'type' => 'review'],
            ['name_ru' => 'Некорректное название магазина',       'name_tk' => 'Dükanyň ady nädogry',                          'type' => 'store'],
            ['name_ru' => 'Логотип нарушает права третьих лиц',   'name_tk' => 'Logotip üçünji taraplaryň hukuklaryny bozýar', 'type' => 'store'],
            ['name_ru' => 'Недостоверный адрес или телефон',      'name_tk' => 'Salgy ýa-da telefon ynamsyz',                  'type' => 'store'],
        ];

        foreach ($rejections as $r) {
            // Причины магазина уже могли прийти из миграции 2026_08_28_000002
            RejectionReason::firstOrCreate(
                ['name_ru' => $r['name_ru'], 'type' => $r['type']],
                $r,
            );
        }
    }
}
