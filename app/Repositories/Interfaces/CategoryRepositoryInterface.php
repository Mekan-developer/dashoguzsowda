<?php

namespace App\Repositories\Interfaces;

use App\Models\Category;
use Illuminate\Database\Eloquent\Collection;

interface CategoryRepositoryInterface
{
    public function tree(): Collection;
    /** Корневые категории — для фильтра в списке объявлений админки. */
    public function roots(): Collection;
    /** Категории с наибольшим числом объявлений — для дашборда. */
    public function topByListings(int $limit = 5): Collection;
    public function activeTree(): Collection;
    public function find(int $id): Category;
    public function create(array $data): Category;
    public function update(Category $category, array $data): Category;
    public function delete(Category $category): void;
    public function siblings(?int $parentId): Collection;
    public function descendants(Category $category): Collection;
    /**
     * Сами категории и все их потомки — id для фильтра выдачи.
     *
     * @param  int[]  $ids
     * @return int[]
     */
    public function subtreeIds(array $ids): array;
}
