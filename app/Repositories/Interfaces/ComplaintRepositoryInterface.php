<?php

namespace App\Repositories\Interfaces;

use App\Models\Complaint;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface ComplaintRepositoryInterface
{
    public function paginate(array $filters, int $perPage = 25): LengthAwarePaginator;
    public function find(int $id): Complaint;
    public function create(array $data): Complaint;
    public function update(Complaint $complaint, array $data): Complaint;
    public function countPending(): int;
    public function countByStatus(string $status): int;
    public function activeReasons(): Collection;
    /** Последние необработанные жалобы для дашборда. */
    public function recentNew(int $limit = 5): Collection;
}
