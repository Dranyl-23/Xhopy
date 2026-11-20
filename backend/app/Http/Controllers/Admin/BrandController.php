<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Catalog\Actions\SaveBrandAction;
use App\Domain\Catalog\Models\Brand;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveBrandRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class BrandController extends Controller
{
    public function index(): Response
    {
        $brands = Brand::query()
            ->withCount('products')
            ->orderBy('name')
            ->get();

        return Inertia::render('admin/catalog/Brands', [
            'brands' => $brands->map(fn (Brand $brand): array => [
                'id' => $brand->id,
                'name' => $brand->name,
                'slug' => $brand->slug,
                'description' => $brand->description ?? '',
                'website_url' => $brand->website_url ?? '',
                'is_active' => $brand->is_active,
                'products_count' => (int) ($brand->products_count ?? 0),
            ])->all(),
        ]);
    }

    public function store(SaveBrandRequest $request, SaveBrandAction $action): RedirectResponse
    {
        $action->execute(null, $request->validated());

        return to_route('admin.catalog.brands.index')
            ->with('success', 'Brand created cleanly.');
    }

    public function update(SaveBrandRequest $request, Brand $brand, SaveBrandAction $action): RedirectResponse
    {
        $action->execute($brand, $request->validated());

        return to_route('admin.catalog.brands.index')
            ->with('success', 'Brand updated cleanly.');
    }

    public function destroy(Brand $brand): RedirectResponse
    {
        $brand->delete();

        return to_route('admin.catalog.brands.index')
            ->with('success', 'Brand removed.');
    }
}
