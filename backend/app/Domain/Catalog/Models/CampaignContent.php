<?php

namespace App\Domain\Catalog\Models;

use App\Domain\Catalog\Enums\CampaignPlacement;
use Database\Factories\Catalog\CampaignContentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property CampaignPlacement $placement
 * @property string $tag
 * @property string $title
 * @property string|null $summary
 * @property string|null $cta_label
 * @property string|null $cta_href
 * @property int|null $category_id
 * @property string|null $image_path
 * @property string|null $image_alt
 * @property int $sort_order
 * @property bool $is_active
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Category|null $category
 */
#[Fillable([
    'placement',
    'tag',
    'title',
    'summary',
    'cta_label',
    'cta_href',
    'category_id',
    'image_path',
    'image_alt',
    'sort_order',
    'is_active',
    'starts_at',
    'ends_at',
])]
class CampaignContent extends Model
{
    /** @use HasFactory<CampaignContentFactory> */
    use HasFactory;

    protected static function newFactory(): CampaignContentFactory
    {
        return CampaignContentFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'placement' => CampaignPlacement::class,
            'category_id' => 'integer',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeCurrentlyActive(Builder $query): Builder
    {
        $now = now();

        return $query
            ->where('is_active', true)
            ->where(function (Builder $q) use ($now): void {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function (Builder $q) use ($now): void {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
            })
            ->orderBy('sort_order')
            ->orderBy('id');
    }
}
