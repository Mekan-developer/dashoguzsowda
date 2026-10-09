<?php

namespace App\Repositories;

use App\Models\Category;
use App\Repositories\Interfaces\CategoryRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class CategoryRepository implements CategoryRepositoryInterface
{
    public function tree(): Collection
    {
        return Category::with('children.children')
            ->whereNull('parent_id')
            ->orderBy('order')
            ->get();
    }

    public function roots(): Collection
    {
        return Category::whereNull('parent_id')->orderBy('order')->get();
    }

    public function topByListings(int $limit = 5): Collection
    {
        return Category::withCount('listings')
            ->orderByDesc('listings_count')
            ->limit($limit)
            ->get();
    }

    public function activeTree(): Collection
    {
        $activeChildren = fn ($q) => $q->where('is_active', true)->orderBy('order');

        return Category::with(['children' => fn ($q) => $activeChildren($q)->with(['children' => $activeChildren])])
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->orderBy('order')
            ->get();
    }

    public function find(int $id): Category
    {
        return Category::findOrFail($id);
    }

    public function create(array $data): Category
    {
        return Category::create($data);
    }

    public function update(Category $category, array $data): Category
    {
        $category->update($data);
        return $category->fresh();
    }

    public function delete(Category $category): void
    {
        $category->delete();
    }

    public function siblings(?int $parentId): Collection
    {
        return Category::where('parent_id', $parentId)
            ->orderBy('order')
            ->get();
    }

    public function descendants(Category $category): Collection
    {
        $all = new Collection();
        $parentIds = [$category->id];

        while ($parentIds !== []) {
            $found = Category::whereIn('parent_id', $parentIds)->get();
            if ($found->isEmpty()) {
                break;
            }
            $all = $all->merge($found);
            $parentIds = $found->pluck('id')->all();
        }

        return $all;
    }

    public function subtreeIds(array $ids): array
    {
        $all = $ids;
        $parentIds = $ids;

        while ($parentIds !== []) {
            $parentIds = Category::whereIn('parent_id', $parentIds)
                ->whereNotIn('id', $all)
                ->pluck('id')
                ->all();
            $all = [...$all, ...$parentIds];
        }

        return array_values(array_unique($all));
    }
}
