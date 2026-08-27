<?php

namespace App\Repositories\Interfaces;

use App\Models\News;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface NewsRepositoryInterface
{
    public function paginate(array $filters, int $perPage = 25): LengthAwarePaginator;
    public function paginateForApi(array $filters, int $perPage = 20): LengthAwarePaginator;
    public function isVisibleToPublic(News $news): bool;
    public function countByPublished(bool $published): int;
    public function find(int $id): News;
    public function create(array $data): News;
    public function update(News $news, array $data): News;
    public function delete(News $news): void;
}
