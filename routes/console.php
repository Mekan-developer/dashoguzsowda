<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// Чистка брошенных временных файлов chunked-загрузки видео
Schedule::command('videos:prune-uploads')->hourly();

// Витрины магазинов гаснут, когда у владельца истекает тариф с can_have_store
Schedule::command('stores:sync-visibility')->dailyAt('03:10');
