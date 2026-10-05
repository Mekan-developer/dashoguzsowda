<?php

namespace App\Observers;

use App\Models\User;
use App\Repositories\Interfaces\TariffRepositoryInterface;

class UserObserver
{
    public function __construct(
        private readonly TariffRepositoryInterface $tariffRepository,
    ) {}

    /**
     * Клиент приложения сразу получает бесплатный тариф — и при регистрации
     * по SMS, и при создании из админки. Бессрочно, без tariff_ends_at.
     * Сотрудникам панели тариф не нужен.
     */
    public function creating(User $user): void
    {
        // role не задан — сработает default колонки, это тоже 'user'
        if (($user->role ?? 'user') !== 'user' || $user->tariff_id !== null) {
            return;
        }

        $free = $this->tariffRepository->getFree();

        if ($free) {
            $user->tariff_id = $free->id;
            $user->tariff_ends_at = null;
        }
    }
}
