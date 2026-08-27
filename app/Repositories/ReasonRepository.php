<?php

namespace App\Repositories;

use App\Models\ComplaintReason;
use App\Models\RejectionReason;
use App\Repositories\Interfaces\ReasonRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class ReasonRepository implements ReasonRepositoryInterface
{
    public function allRejectionReasons(): Collection
    {
        return RejectionReason::orderBy('type')->orderBy('id')->get();
    }

    public function rejectionReasonsByType(string $type): Collection
    {
        return RejectionReason::where('type', $type)->orderBy('id')->get();
    }

    public function activeRejectionReasons(string $type): Collection
    {
        return RejectionReason::where('type', $type)
            ->where('is_active', true)
            ->orderBy('id')
            ->get();
    }

    public function createRejectionReason(array $data): RejectionReason
    {
        return RejectionReason::create($data);
    }

    public function updateRejectionReason(RejectionReason $reason, array $data): RejectionReason
    {
        $reason->update($data);

        return $reason->fresh();
    }

    public function deleteRejectionReason(RejectionReason $reason): void
    {
        $reason->delete();
    }

    public function allComplaintReasons(): Collection
    {
        return ComplaintReason::orderBy('id')->get();
    }

    public function createComplaintReason(array $data): ComplaintReason
    {
        return ComplaintReason::create($data);
    }

    public function updateComplaintReason(ComplaintReason $reason, array $data): ComplaintReason
    {
        $reason->update($data);

        return $reason->fresh();
    }

    public function deleteComplaintReason(ComplaintReason $reason): void
    {
        $reason->delete();
    }
}
