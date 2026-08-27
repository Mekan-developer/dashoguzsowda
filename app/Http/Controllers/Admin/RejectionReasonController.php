<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreRejectionReasonRequest;
use App\Http\Requests\Admin\UpdateRejectionReasonRequest;
use App\Models\RejectionReason;
use App\Repositories\Interfaces\ReasonRepositoryInterface;
use Inertia\Inertia;

class RejectionReasonController extends Controller
{
    public function __construct(
        private readonly ReasonRepositoryInterface $reasons,
    ) {}

    public function index()
    {
        return Inertia::render('RejectionReasons/Index', [
            'reasons' => $this->reasons->allRejectionReasons(),
        ]);
    }

    public function store(StoreRejectionReasonRequest $request)
    {
        $this->reasons->createRejectionReason($request->validated());

        return back()->with('toast', ['type' => 'success', 'message' => __('messages.created')]);
    }

    public function update(UpdateRejectionReasonRequest $request, RejectionReason $rejectionReason)
    {
        $this->reasons->updateRejectionReason($rejectionReason, $request->validated());

        return back()->with('toast', ['type' => 'success', 'message' => __('messages.updated')]);
    }

    public function destroy(RejectionReason $rejectionReason)
    {
        $this->reasons->deleteRejectionReason($rejectionReason);

        return back()->with('toast', ['type' => 'success', 'message' => __('messages.deleted')]);
    }
}
