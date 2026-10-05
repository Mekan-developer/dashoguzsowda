<?php

namespace App\Actions;

use App\Events\TariffRequestApproved;
use App\Models\TariffRequest;
use App\Models\User;
use App\Services\TariffRequestService;
use Illuminate\Validation\ValidationException;

/**
 * Админ получил деньги и подтверждает заявку: тариф выдаётся на duration_days.
 * Витрину магазина и скрытый по тарифу контент возвращает AssignTariffAction.
 */
class ApproveTariffRequestAction
{
    public function __construct(
        private readonly TariffRequestService $tariffRequestService,
        private readonly AssignTariffAction $assignTariffAction,
    ) {}

    public function execute(TariffRequest $request, User $admin): TariffRequest
    {
        if ($request->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => __('messages.tariff_request_already_processed'),
            ]);
        }

        $tariff = $request->tariff;

        if (! $tariff) {
            throw ValidationException::withMessages([
                'tariff_id' => __('messages.tariff_request_tariff_missing'),
            ]);
        }

        $this->assignTariffAction->execute($request->user, $tariff);

        $processed = $this->tariffRequestService->markApproved($request, $admin);

        event(new TariffRequestApproved($processed));

        return $processed;
    }
}
