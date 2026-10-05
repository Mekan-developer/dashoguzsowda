<?php

namespace App\Events;

use App\Models\Tariff;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Платный тариф истёк, пользователь переведён на бесплатный.
 * Счётчики — сколько объявлений и роликов скрыто сверх его лимитов.
 */
class TariffExpired
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly ?Tariff $expiredTariff,
        public readonly int $suspendedListings,
        public readonly int $suspendedVideos,
    ) {}
}
