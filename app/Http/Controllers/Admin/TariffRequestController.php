<?php

namespace App\Http\Controllers\Admin;

use App\Actions\ApproveTariffRequestAction;
use App\Actions\RejectTariffRequestAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RejectTariffRequestRequest;
use App\Models\TariffRequest;
use App\Services\TariffRequestService;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Заявки на платный тариф. Оплата принимается наличными вне системы, поэтому
 * «Подтвердить» здесь означает «деньги получены» — только после него тариф
 * реально включается пользователю.
 */
class TariffRequestController extends Controller
{
    public function __construct(
        private readonly TariffRequestService $tariffRequestService,
        private readonly ApproveTariffRequestAction $approveTariffRequest,
        private readonly RejectTariffRequestAction $rejectTariffRequest,
    ) {}

    public function index(Request $request)
    {
        return Inertia::render('TariffRequests/Index', [
            'requests' => $this->tariffRequestService->list($request->only('status', 'search')),
            'counts'   => ['pending' => $this->tariffRequestService->countPending()],
            'filters'  => $request->only('status', 'search'),
        ]);
    }

    public function approve(Request $request, TariffRequest $tariffRequest)
    {
        $this->approveTariffRequest->execute($tariffRequest, $request->user());

        return back()->with('toast', ['type' => 'success', 'message' => __('messages.tariff_assigned')]);
    }

    public function reject(RejectTariffRequestRequest $request, TariffRequest $tariffRequest)
    {
        $this->rejectTariffRequest->execute(
            $tariffRequest,
            $request->user(),
            $request->validated('comment'),
        );

        return back()->with('toast', ['type' => 'success', 'message' => __('messages.tariff_request_rejected')]);
    }
}
