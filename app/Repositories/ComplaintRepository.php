<?php

namespace App\Repositories;

use App\Models\Complaint;
use App\Models\ComplaintReason;
use App\Repositories\Interfaces\ComplaintRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class ComplaintRepository implements ComplaintRepositoryInterface
{
    public function paginate(array $filters, int $perPage = 25): LengthAwarePaginator
    {
        return Complaint::with('user', 'listing', 'complaintReason', 'resolver')
            ->when($filters['status'] ?? null, fn($q, $s) => $q->where('status', $s))
            ->when($filters['reason_id'] ?? null, fn($q, $r) => $q->where('complaint_reason_id', $r))
            ->when($filters['search'] ?? null, fn($q, $s) => $q->where(function ($q) use ($s) {
                $q->where('text', 'like', "%$s%")
                  ->orWhereHas('user', fn($u) => $u->where('name', 'like', "%$s%")->orWhere('phone', 'like', "%$s%"))
                  ->orWhereHas('listing', fn($l) => $l->where('title', 'like', "%$s%"));
            }))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    public function find(int $id): Complaint
    {
        return Complaint::with('user', 'listing', 'complaintReason')->findOrFail($id);
    }

    public function create(array $data): Complaint
    {
        return Complaint::create($data);
    }

    public function update(Complaint $complaint, array $data): Complaint
    {
        $complaint->update($data);
        return $complaint->fresh();
    }

    public function countPending(): int
    {
        return Complaint::where('status', 'new')->count();
    }

    public function countByStatus(string $status): int
    {
        return Complaint::where('status', $status)->count();
    }

    public function recentNew(int $limit = 5): Collection
    {
        return Complaint::with('user', 'listing', 'complaintReason')
            ->where('status', 'new')
            ->latest()
            ->limit($limit)
            ->get();
    }

    public function activeReasons(): Collection
    {
        return ComplaintReason::where('is_active', true)->get();
    }
}
