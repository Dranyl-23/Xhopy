<?php

namespace App\Domain\Catalog\Actions;

use App\Domain\Catalog\Models\Brand;
use Illuminate\Support\Str;

final class SaveBrandAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(?Brand $brand, array $data): Brand
    {
        $slug = trim((string) ($data['slug'] ?? ''));
        if ($slug === '') {
            $slug = Str::slug((string) $data['name']);
        }

        $target = $brand ?? new Brand;
        $target->fill([
            'name' => trim((string) $data['name']),
            'slug' => $slug,
            'description' => isset($data['description']) ? trim((string) $data['description']) : null,
            'website_url' => isset($data['website_url']) && $data['website_url'] !== '' ? trim((string) $data['website_url']) : null,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);
        $target->save();

        return $target;
    }
}
