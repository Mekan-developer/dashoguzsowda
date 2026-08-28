<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Магазин теперь проходит модерацию наравне с объявлениями/роликами/отзывами,
 * значит его отказ тоже нуждается в причине из общего справочника.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rejection_reasons', function (Blueprint $table) {
            $table->enum('type', ['listing', 'video', 'review', 'store'])->default('listing')->change();
        });

        $now = now();

        // Стартовый набор причин: без них админ не сможет отклонить первый же магазин.
        foreach ([
            ['name_ru' => 'Некорректное название магазина', 'name_tk' => 'Dükanyň ady nädogry'],
            ['name_ru' => 'Логотип нарушает права третьих лиц', 'name_tk' => 'Logotip üçünji taraplaryň hukuklaryny bozýar'],
            ['name_ru' => 'Недостоверный адрес или телефон', 'name_tk' => 'Salgy ýa-da telefon ynamsyz'],
        ] as $reason) {
            $exists = DB::table('rejection_reasons')
                ->where('type', 'store')
                ->where('name_ru', $reason['name_ru'])
                ->exists();

            if (! $exists) {
                DB::table('rejection_reasons')->insert([
                    ...$reason,
                    'type'       => 'store',
                    'is_active'  => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('rejection_reasons')->where('type', 'store')->delete();

        Schema::table('rejection_reasons', function (Blueprint $table) {
            $table->enum('type', ['listing', 'video', 'review'])->default('listing')->change();
        });
    }
};
