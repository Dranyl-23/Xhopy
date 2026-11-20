<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Catalog\Actions\SaveCategoryAction;
use App\Domain\Catalog\Models\Category;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveCategoryRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function index(): Response
    {
        $categories = Category::query()
            ->with('parent')
            ->withCount('products')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return Inertia::render('admin/catalog/Categories', [
            'categories' => $categories->map(fn (Category $category): array => [
                'id' => $category->id,
                'parent_id' => $category->parent_id,
                'parent_name' => $category->parent?->name,
                'name' => $category->name,
                'slug' => $category->slug,
                'description' => $category->description ?? '',
                'sort_order' => $category->sort_order,
                'is_active' => $category->is_active,
                'products_count' => (int) ($category->products_count ?? 0),
            ])->all(),
        ]);
    }

    public function store(SaveCategoryRequest $request, SaveCategoryAction $action): RedirectResponse
    {
        $action->execute(null, $request->validated());

        return to_route('admin.catalog.categories.index')
            ->with('success', 'Category created cleanly.');
    }

    public function update(SaveCategoryRequest $request, Category $category, SaveCategoryAction $action): RedirectResponse
    {
        $action->execute($category, $request->validated());

        return to_route('admin.catalog.categories.index')
            ->with('success', 'Category updated cleanly.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        if ($category->products()->exists()) {
            return back()->withErrors([
                'category' => 'Cannot delete a category that still contains products.',
            ]);
        }

        $category->delete();

        return to_route('admin.catalog.categories.index')
            ->with('success', 'Category deleted.');
    }
}
