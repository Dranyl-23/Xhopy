<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Catalog\Actions\SaveCampaignContentAction;
use App\Domain\Catalog\Enums\CampaignPlacement;
use App\Domain\Catalog\Models\CampaignContent;
use App\Domain\Catalog\Models\Category;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveCampaignContentRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CampaignContentController extends Controller
{
    public function index(): Response
    {
        $campaigns = CampaignContent::query()
            ->with('category')
            ->orderBy('placement')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return Inertia::render('admin/catalog/Campaigns', [
            'campaigns' => $campaigns->map(fn (CampaignContent $campaign): array => [
                'id' => $campaign->id,
                'placement' => $campaign->placement->value,
                'placementLabel' => $campaign->placement->label(),
                'tag' => $campaign->tag,
                'title' => $campaign->title,
                'summary' => $campaign->summary ?? '',
                'cta_label' => $campaign->cta_label ?? '',
                'cta_href' => $campaign->cta_href ?? '',
                'category_id' => $campaign->category_id,
                'category_name' => $campaign->category?->name,
                'sort_order' => $campaign->sort_order,
                'is_active' => $campaign->is_active,
            ])->all(),
            'categories' => Category::query()->orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'slug']),
            'placements' => array_map(
                static fn (CampaignPlacement $placement): array => [
                    'value' => $placement->value,
                    'label' => $placement->label(),
                ],
                CampaignPlacement::cases(),
            ),
        ]);
    }

    public function store(SaveCampaignContentRequest $request, SaveCampaignContentAction $action): RedirectResponse
    {
        $action->execute(null, $request->validated());

        return to_route('admin.catalog.campaigns.index')
            ->with('success', 'Campaign content saved.');
    }

    public function update(
        SaveCampaignContentRequest $request,
        CampaignContent $campaign,
        SaveCampaignContentAction $action,
    ): RedirectResponse {
        $action->execute($campaign, $request->validated());

        return to_route('admin.catalog.campaigns.index')
            ->with('success', 'Campaign content updated.');
    }

    public function destroy(CampaignContent $campaign): RedirectResponse
    {
        $campaign->delete();

        return to_route('admin.catalog.campaigns.index')
            ->with('success', 'Campaign content deleted.');
    }
}
