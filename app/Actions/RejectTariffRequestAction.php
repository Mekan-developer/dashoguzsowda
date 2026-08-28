<?php

namespace App\Actions;

use App\Events\TariffRequestRejected;
use App\Models\TariffRequest;
use App\Models\User;
use App\Services\TariffRequestService;
use Illuminate\Validation\ValidationException;

class RejectTariffRequestAction
{
    public function __construct(
        private readonly TariffRequestService $tariffRequestService,
    ) {}

    public function execute(TariffRequest $request, User $admin, ?string $comment = null): TariffRequest
    {
        if ($request->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => __('messages.tariff_request_already_processed'),
            ]);
        }

        $processed = $this->tariffRequestService->markRejected($request, $admin, $comment);

        event(new TariffRequestRejected($processed));

        return $processed;
    }
}
