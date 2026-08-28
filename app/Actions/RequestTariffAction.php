<?php

namespace App\Actions;

use App\Models\Tariff;
use App\Models\TariffRequest;
use App\Models\User;
use App\Services\TariffRequestService;
use Illuminate\Validation\ValidationException;

/**
 * PUT /v1/profile/subscription: пользователь выбирает тариф в приложении.
 *
 * Платный тариф здесь НЕ выдаётся — создаётся заявка, а активирует её админ
 * после того, как получит оплату наличными. Бесплатный назначается сразу:
 * денег он не требует, и это способ отказаться от платного.
 */
class RequestTariffAction
{
    public function __construct(
        private readonly TariffRequestService $tariffRequestService,
        private readonly AssignTariffAction $assignTariffAction,
    ) {}

    /** @return TariffRequest|null null — тариф назначен сразу (бесплатный) */
    public function execute(User $user, Tariff $tariff): ?TariffRequest
    {
        if ($tariff->is_free) {
            $this->assignTariffAction->execute($user, $tariff);

            return null;
        }

        if ($this->tariffRequestService->pendingForUser($user)) {
            throw ValidationException::withMessages([
                'tariff_name' => __('messages.tariff_request_already_pending'),
            ]);
        }

        return $this->tariffRequestService->create($user, $tariff);
    }
}
