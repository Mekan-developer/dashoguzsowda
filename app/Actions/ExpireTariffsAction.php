<?php

namespace App\Actions;

use App\Events\TariffExpired;
use App\Models\User;
use App\Repositories\Interfaces\TariffRepositoryInterface;
use App\Repositories\Interfaces\UserRepositoryInterface;

/**
 * Платный тариф истекает по времени, без действия пользователя, — поэтому
 * переход на бесплатный идёт по расписанию (tariffs:expire). Лишние
 * объявления и ролики скрываются, витрина гаснет (AssignTariffAction);
 * оплатит снова — всё вернётся при подтверждении заявки.
 */
class ExpireTariffsAction
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly TariffRepositoryInterface $tariffRepository,
        private readonly AssignTariffAction $assignTariffAction,
    ) {}

    /** @return int|null сколько пользователей переведено; null — бесплатного тарифа нет */
    public function execute(): ?int
    {
        $free = $this->tariffRepository->getFree();

        // Без бесплатного тарифа переводить некуда — истёкшие остаются как есть,
        // activeTariff() для них и так вернёт null (лимиты 0)
        if (! $free) {
            return null;
        }

        $expired = 0;

        $this->userRepository->chunkWithExpiredTariff(function ($users) use ($free, &$expired) {
            /** @var User $user */
            foreach ($users as $user) {
                $previous = $user->tariff;

                $result = $this->assignTariffAction->execute($user, $free);

                event(new TariffExpired(
                    $user,
                    $previous,
                    $result['suspended']['listings'],
                    $result['suspended']['videos'],
                ));

                $expired++;
            }
        });

        return $expired;
    }
}
