<?php

namespace App\Actions;

use App\Models\User;

class CheckStoreTariffAction
{
    /**
     * Магазин доступен только на тарифе с can_have_store (сейчас — Premium).
     * Проверяется при создании и при любой правке своего магазина: тариф мог
     * кончиться уже после того, как магазин был заведён.
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException 403
     */
    public function execute(User $user): void
    {
        if (! $user->activeTariff()?->canHaveStore()) {
            abort(403, __('messages.store_requires_premium_tariff'));
        }
    }
}
