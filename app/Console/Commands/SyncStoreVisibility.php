<?php

namespace App\Console\Commands;

use App\Services\StoreService;
use Illuminate\Console\Command;

/**
 * Тариф истекает по времени, без действия пользователя, — значит витрину надо
 * гасить по расписанию. Магазин при этом не удаляется: он вернётся в выдачу
 * сам, как только владелец снова оплатит тариф с can_have_store.
 */
class SyncStoreVisibility extends Command
{
    protected $signature = 'stores:sync-visibility';

    protected $description = 'Гасит витрины магазинов, у владельцев которых истёк тариф с правом на магазин';

    public function handle(StoreService $storeService): int
    {
        $changed = $storeService->syncAllVisibility();

        $this->info("Витрин изменено: {$changed}");

        return self::SUCCESS;
    }
}
