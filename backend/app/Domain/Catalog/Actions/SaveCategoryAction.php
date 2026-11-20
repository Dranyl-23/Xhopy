<?php

namespace App\Domain\Catalog\Actions;

use App\Domain\Catalog\Models\Category;
use Illuminate\Support\Str;

final class SaveCategoryAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(?Category $category, array $data): Category
    {
        $slug = trim((string) ($data['slug'] ?? ''));
        if ($slug === '') {
            $slug = Str::slug((string) $data['name']);
        }

        $target = $category ?? new Category;
        $target->fill([
            'parent_id' => isset($data['parent_id']) && $data['parent_id'] !== '' ? (int) $data['parent_id'] : null,
            'name' => trim((string) $data['name']),
            'slug' => $slug,
            'description' => isset($data['description']) ? trim((string) $data['description']) : null,
            'sort_order' => max(0, (int) ($data['sort_order'] ?? 0)),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);
        $target->save();

        return $target;
    }
}
