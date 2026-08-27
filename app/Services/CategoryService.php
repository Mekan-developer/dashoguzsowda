<?php

namespace App\Services;

use App\Actions\UploadCategoryIconAction;
use App\Actions\UploadCategoryImageAction;
use App\Models\Category;
use App\Repositories\Interfaces\CategoryIconRepositoryInterface;
use App\Repositories\Interfaces\CategoryRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CategoryService
{
    public function __construct(
        private readonly CategoryRepositoryInterface $categories,
        private readonly CategoryIconRepositoryInterface $icons,
        private readonly UploadCategoryIconAction $uploadIcon,
        private readonly UploadCategoryImageAction $uploadImage,
    ) {}

    public function tree(): Collection
    {
        return $this->categories->tree();
    }

    /** Дерево категорий для мобильного приложения: скрытый родитель скрывает всех потомков. */
    public function activeTree(): Collection
    {
        return $this->categories->activeTree();
    }

    public function iconLibrary(): Collection
    {
        return $this->icons->all();
    }

    public function create(array $data, ?UploadedFile $icon = null, ?UploadedFile $image = null, array $crop = []): Category
    {
        $parentId = $data['parent_id'] ?? null;
        $this->assertValidParent($parentId);

        $data['level'] = $this->levelFor($parentId);
        $data['slug'] = Str::slug($data['name_ru']).'-'.Str::random(6);
        $data['order'] = ($this->categories->siblings($parentId)->max('order') ?? 0) + 1;

        if ($icon) {
            $data['icon_path'] = $this->uploadIcon->execute($icon);
        }

        if ($image) {
            $data['image_path'] = $this->storeImage($image, $crop);
        }

        return $this->categories->create($data);
    }

    public function update(Category $category, array $data, ?UploadedFile $icon = null, ?UploadedFile $image = null, array $crop = []): Category
    {
        $reparented = array_key_exists('parent_id', $data);

        if ($reparented) {
            $this->assertValidParent($data['parent_id'], $category);
            $data['level'] = $this->levelFor($data['parent_id']);
        }

        if ($icon) {
            $data['icon_path'] = $this->uploadIcon->execute($icon);
        }

        if ($image) {
            if ($category->image_path) {
                Storage::disk('public')->delete($category->image_path);
            }
            $data['image_path'] = $this->storeImage($image, $crop);
        } elseif ($reparented && $data['parent_id'] !== null && $category->image_path) {
            // Изображение доступно только для корневых категорий — при переносе под родителя чистим его.
            Storage::disk('public')->delete($category->image_path);
            $data['image_path'] = null;
        }

        $updated = $this->categories->update($category, $data);

        if ($reparented) {
            $this->recomputeDescendantLevels($updated);
        }

        return $updated;
    }

    private function storeImage(UploadedFile $image, array $crop): string
    {
        return $this->uploadImage->execute(
            $image,
            (float) ($crop['crop_x'] ?? 50),
            (float) ($crop['crop_y'] ?? 50),
        );
    }

    public function toggleActive(Category $category): Category
    {
        return $this->categories->update($category, ['is_active' => ! $category->is_active]);
    }

    public function move(Category $category, string $direction): void
    {
        $siblings = $this->categories->siblings($category->parent_id)->values();
        $index = $siblings->search(fn ($c) => $c->id === $category->id);
        $targetIndex = $direction === 'up' ? $index - 1 : $index + 1;

        if ($index === false || ! $siblings->has($targetIndex)) {
            return;
        }

        $target = $siblings[$targetIndex];
        $order = $category->order;

        $this->categories->update($category, ['order' => $target->order]);
        $this->categories->update($target, ['order' => $order]);
    }

    public function delete(Category $category): void
    {
        $imagePath = $category->image_path;

        try {
            $this->categories->delete($category);
        } catch (QueryException) {
            throw ValidationException::withMessages([
                'category' => __('messages.category_has_listings'),
            ]);
        }

        if ($imagePath) {
            Storage::disk('public')->delete($imagePath);
        }
    }

    private function assertValidParent(?int $parentId, ?Category $current = null): void
    {
        if ($parentId === null) {
            return;
        }

        if ($current && $parentId === $current->id) {
            throw ValidationException::withMessages(['parent_id' => __('messages.category_parent_invalid')]);
        }

        $parent = $this->categories->find($parentId);

        if ($parent->level >= Category::MAX_LEVEL) {
            throw ValidationException::withMessages(['parent_id' => __('messages.category_max_level')]);
        }

        if ($current) {
            $descendants = $this->categories->descendants($current);

            if ($descendants->contains('id', $parentId)) {
                throw ValidationException::withMessages(['parent_id' => __('messages.category_parent_invalid')]);
            }

            if ($descendants->isNotEmpty()) {
                $relativeDepth = $descendants->max('level') - $current->level;
                if ($parent->level + 1 + $relativeDepth > Category::MAX_LEVEL) {
                    throw ValidationException::withMessages(['parent_id' => __('messages.category_max_level')]);
                }
            }
        }
    }

    private function levelFor(?int $parentId): int
    {
        return $parentId === null ? 1 : $this->categories->find($parentId)->level + 1;
    }

    private function recomputeDescendantLevels(Category $category): void
    {
        foreach ($category->children()->get() as $child) {
            $updatedChild = $this->categories->update($child, ['level' => $category->level + 1]);
            $this->recomputeDescendantLevels($updatedChild);
        }
    }
}
