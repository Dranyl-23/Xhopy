<?php

namespace App\Domain\Catalog\Actions;

use App\Domain\Catalog\Enums\CampaignPlacement;
use App\Domain\Catalog\Models\CampaignContent;

final class SaveCampaignContentAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(?CampaignContent $campaign, array $data): CampaignContent
    {
        $placement = $data['placement'] instanceof CampaignPlacement
            ? $data['placement']
            : CampaignPlacement::from((string) $data['placement']);

        $target = $campaign ?? new CampaignContent;
        $target->fill([
            'placement' => $placement,
            'tag' => trim((string) $data['tag']),
            'title' => trim((string) $data['title']),
            'summary' => isset($data['summary']) ? trim((string) $data['summary']) : null,
            'cta_label' => isset($data['cta_label']) ? trim((string) $data['cta_label']) : null,
            'cta_href' => isset($data['cta_href']) && $data['cta_href'] !== '' ? trim((string) $data['cta_href']) : null,
            'category_id' => isset($data['category_id']) && $data['category_id'] !== '' ? (int) $data['category_id'] : null,
            'image_alt' => isset($data['image_alt']) ? trim((string) $data['image_alt']) : null,
            'sort_order' => max(0, (int) ($data['sort_order'] ?? 0)),
            'is_active' => (bool) ($data['is_active'] ?? true),
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
        ]);
        $target->save();

        return $target;
    }
}
