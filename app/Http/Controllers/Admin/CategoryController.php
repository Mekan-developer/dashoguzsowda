<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCategoryRequest;
use App\Models\Category;
use App\Services\CategoryService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CategoryController extends Controller
{
    public function __construct(
        private readonly CategoryService $categoryService,
    ) {}

    public function index()
    {
        return Inertia::render('Categories/Index', [
            'categories' => $this->categoryService->tree(),
            'icons'      => $this->categoryService->iconLibrary(),
        ]);
    }

    public function store(StoreCategoryRequest $request)
    {
        $data = $request->safe()->except('icon');
        $this->categoryService->create($data, $request->file('icon'));

        return back()->with('toast', ['type' => 'success', 'message' => __('messages.created')]);
    }

    public function update(StoreCategoryRequest $request, Category $category)
    {
        $data = $request->safe()->except('icon');
        $this->categoryService->update($category, $data, $request->file('icon'));

        return back()->with('toast', ['type' => 'success', 'message' => __('messages.updated')]);
    }

    public function destroy(Request $request, Category $category)
    {
        $this->categoryService->delete($category);

        return back()->with('toast', ['type' => 'success', 'message' => __('messages.deleted')]);
    }

    public function toggle(Category $category)
    {
        $this->categoryService->toggleActive($category);

        return back()->with('toast', ['type' => 'success', 'message' => __('messages.updated')]);
    }

    public function move(Request $request, Category $category)
    {
        $request->validate(['direction' => 'required|in:up,down']);
        $this->categoryService->move($category, $request->input('direction'));

        return back();
    }
}
