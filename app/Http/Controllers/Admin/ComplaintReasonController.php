<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreComplaintReasonRequest;
use App\Http\Requests\Admin\UpdateComplaintReasonRequest;
use App\Models\ComplaintReason;
use App\Repositories\Interfaces\ReasonRepositoryInterface;
use Inertia\Inertia;

class ComplaintReasonController extends Controller
{
    public function __construct(
        private readonly ReasonRepositoryInterface $reasons,
    ) {}

    public function index()
    {
        return Inertia::render('ComplaintReasons/Index', [
            'reasons' => $this->reasons->allComplaintReasons(),
        ]);
    }

    public function store(StoreComplaintReasonRequest $request)
    {
        $this->reasons->createComplaintReason($request->validated());

        return back()->with('toast', ['type' => 'success', 'message' => __('messages.created')]);
    }

    public function update(UpdateComplaintReasonRequest $request, ComplaintReason $complaintReason)
    {
        $this->reasons->updateComplaintReason($complaintReason, $request->validated());

        return back()->with('toast', ['type' => 'success', 'message' => __('messages.updated')]);
    }

    public function destroy(ComplaintReason $complaintReason)
    {
        $this->reasons->deleteComplaintReason($complaintReason);

        return back()->with('toast', ['type' => 'success', 'message' => __('messages.deleted')]);
    }
}
