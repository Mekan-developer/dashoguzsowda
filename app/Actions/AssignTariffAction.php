<?php

namespace App\Actions;

use App\Models\Tariff;
use App\Models\User;
use App\Services\StoreService;
use App\Services\TariffService;

class AssignTariffAction
{
    public function __construct(
        private readonly TariffService $tariffService,
        private readonly SyncTariffContentAction $syncTariffContentAction,
        private readonly StoreService $storeService,
    ) {}

    /**
     * Любая смена тарифа идёт здесь (админ, заявка, бесплатный по запросу,
     * истечение платного), поэтому здесь же всё, что от тарифа зависит,
     * подгоняется под новые права: лишний контент скрывается, скрытый
     * возвращается, витрина магазина гаснет или зажигается.
     *
     * @return array{suspended: array{listings: int, videos: int}, restored: array{listings: int, videos: int}}
     */
    public function execute(User $user, Tariff $tariff): array
    {
        // Репозиторий обновляет этот же экземпляр — activeTariff() ниже видит новый тариф
        $this->tariffService->assignToUser($user, $tariff);

        $this->storeService->syncVisibility($user);

        return $this->syncTariffContentAction->execute($user);
    }
}
