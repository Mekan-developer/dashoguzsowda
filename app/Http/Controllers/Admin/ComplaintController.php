<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ResolveComplaintRequest;
use App\Models\Complaint;
use App\Repositories\Interfaces\ComplaintRepositoryInterface;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ComplaintController extends Controller
{
    public function __construct(
        private readonly ComplaintRepositoryInterface $complaintRepository,
        private readonly NotificationService $notificationService,
    ) {}

    public function index(Request $request)
    {
        $this->notificationService->markSectionSeen($request->user(), 'complaints');

        return Inertia::render('Complaints/Index', [
            'complaints' => $this->complaintRepository->paginate($request->only('status', 'search', 'reason_id')),
            'reasons'    => $this->complaintRepository->activeReasons(),
            'filters'    => $request->only('status', 'search', 'reason_id'),
            'counts'     => [
                'pending'  => $this->complaintRepository->countPending(),
                'resolved' => $this->complaintRepository->countByStatus('resolved'),
            ],
        ]);
    }

    public function resolve(ResolveComplaintRequest $request, Complaint $complaint)
    {
        $this->complaintRepository->update($complaint, [
            'status'          => 'resolved',
            'resolved_by'     => $request->user()->id,
            'resolution_note' => $request->validated('resolution_note'),
        ]);

        return back()->with('toast', ['type' => 'success', 'message' => __('messages.complaint_resolved')]);
    }
}
