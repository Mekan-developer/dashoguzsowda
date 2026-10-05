<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// Чистка брошенных временных файлов chunked-загрузки видео
Schedule::command('videos:prune-uploads')->hourly();

// Истёкший платный тариф → бесплатный, контент сверх лимитов скрывается.
// До sync-visibility: витрину переводимых гасит уже сам перевод.
Schedule::command('tariffs:expire')->dailyAt('03:00');

// Витрины магазинов гаснут, когда у владельца истекает тариф с can_have_store
Schedule::command('stores:sync-visibility')->dailyAt('03:10');
