<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Магазин может относиться к нескольким категориям (мобилка шлёт category_ids[]).
 * stores.category_id остаётся «главной» (первая из списка) — для subtitle и
 * обратной совместимости фильтра / старых клиентов.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_store', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();

            $table->unique(['store_id', 'category_id']);
        });

        $rows = DB::table('stores')
            ->whereNotNull('category_id')
            ->get(['id', 'category_id'])
            ->map(fn ($store) => [
                'store_id'    => $store->id,
                'category_id' => $store->category_id,
            ])
            ->all();

        if ($rows !== []) {
            DB::table('category_store')->insert($rows);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('category_store');
    }
};
