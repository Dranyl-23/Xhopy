<?php

namespace App\Http\Controllers\Storefront;

use App\Domain\Cart\Services\CartService;
use App\Domain\Catalog\Services\CatalogQueryService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __construct(
        private readonly CatalogQueryService $catalogQuery,
        private readonly CartService $cartService,
    ) {}

    /**
     * Render the XHopy storefront home page.
     *
     * Serves only real active database catalog & campaigns by default.
     * In non-production environments, passing `?preview_demo=1` loads clearly labeled
     * [DEMO] preview items to verify the design system components without altering DB state.
     */
    public function __invoke(Request $request): Response
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', 'string', 'max:80', 'alpha_dash'],
            'preview_demo' => ['nullable', 'boolean'],
        ]);

        $searchQuery = isset($validated['q']) ? trim((string) $validated['q']) : '';
        $selectedCategory = isset($validated['category']) ? (string) $validated['category'] : null;
        $isDemoPreview = ! app()->isProduction() && $request->boolean('preview_demo');

        if ($isDemoPreview) {
            $categories = $this->demoCategories();
            $topProducts = $this->filterDemoProducts($this->demoProducts(), $searchQuery, $selectedCategory);
            $announcement = [
                'id' => 'demo-announcement',
                'message' => '[DEMO PREVIEW] Free standard delivery on verified orders within Metro Manila.',
                'linkText' => 'Explore Top Products',
                'linkHref' => '#top-products-heading',
            ];
            $dailyInterest = $this->demoDailyInterest();
        } else {
            $realCatalog = $this->catalogQuery->homeCatalog($searchQuery, $selectedCategory);
            $categories = $realCatalog['categories'];
            $topProducts = $realCatalog['topProducts'];
            $announcement = $realCatalog['announcement'];
            $dailyInterest = $realCatalog['dailyInterest'];
        }

        return Inertia::render('Welcome', [
            'announcement' => $announcement,
            'hero' => [
                'badgeText' => 'XHopy Official Store',
                'eyebrow' => 'Curated Everyday Essentials',
                'title' => 'Thoughtfully engineered essentials for modern living.',
                'subtitle' => 'Transparent pricing, real-time inventory verification, and seamless checkout built on a fast, accessible storefront.',
                'primaryCtaLabel' => 'Browse Catalog',
                'primaryCtaHref' => route('catalog.index'),
                'secondaryCtaLabel' => 'Explore Categories',
                'secondaryCtaHref' => '#categories-heading',
            ],
            'categories' => $categories,
            'topProducts' => $topProducts,
            'dailyInterest' => $dailyInterest,
            'cartSummary' => $isDemoPreview
                ? ['itemCount' => 0, 'subtotalFormatted' => null, 'items' => []]
                : $this->cartService->summaryForRequest($request),
            'searchQuery' => $searchQuery,
            'selectedCategory' => $selectedCategory,
            'isDemoPreview' => $isDemoPreview,
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function demoCategories(): array
    {
        return [
            [
                'id' => 'cat-audio',
                'name' => '[DEMO] Audio & Acoustics',
                'slug' => 'audio-acoustics',
                'description' => 'Studio headphones, DACs, and desktop monitors.',
                'productCount' => 2,
                'imageUrl' => null,
            ],
            [
                'id' => 'cat-workspace',
                'name' => '[DEMO] Workspace & Desk',
                'slug' => 'workspace-desk',
                'description' => 'Ergonomic lighting, mechanical keyboards, and stands.',
                'productCount' => 1,
                'imageUrl' => null,
            ],
            [
                'id' => 'cat-carry',
                'name' => '[DEMO] Everyday Carry',
                'slug' => 'everyday-carry',
                'description' => 'Weatherproof packs, pouches, and travel organizers.',
                'productCount' => 1,
                'imageUrl' => null,
            ],
            [
                'id' => 'cat-home',
                'name' => '[DEMO] Home Living',
                'slug' => 'home-living',
                'description' => 'Minimalist drinkware and smart ambient essentials.',
                'productCount' => 0,
                'imageUrl' => null,
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function demoProducts(): array
    {
        return [
            [
                'id' => 'demo-prod-1',
                'name' => '[DEMO] XHopy Reference Studio Headphones',
                'slug' => 'demo-xhopy-reference-studio-headphones',
                'sku' => 'DEMO-AUD-001',
                'url' => null,
                'categoryName' => 'Audio & Acoustics',
                'categorySlug' => 'audio-acoustics',
                'sellerName' => 'XHopy Official',
                'unitPrice' => 6490.00,
                'priceFormatted' => '₱6,490.00',
                'originalPrice' => 7490.00,
                'originalPriceFormatted' => '₱7,490.00',
                'discountLabel' => 'Save ₱1,000',
                'currency' => 'PHP',
                'inStock' => true,
                'stockLabel' => 'In Stock',
                'imageUrl' => null,
                'imageAlt' => '[DEMO] XHopy Reference Studio Headphones in matte charcoal',
                'ratingAverage' => null,
                'ratingCount' => null,
            ],
            [
                'id' => 'demo-prod-2',
                'name' => '[DEMO] Precision Aluminum Monitor Light Bar',
                'slug' => 'demo-precision-aluminum-monitor-light-bar',
                'sku' => 'DEMO-WRK-002',
                'url' => null,
                'categoryName' => 'Workspace & Desk',
                'categorySlug' => 'workspace-desk',
                'sellerName' => 'XHopy Official',
                'unitPrice' => 2850.00,
                'priceFormatted' => '₱2,850.00',
                'originalPrice' => null,
                'originalPriceFormatted' => null,
                'discountLabel' => null,
                'currency' => 'PHP',
                'inStock' => true,
                'stockLabel' => 'In Stock',
                'imageUrl' => null,
                'imageAlt' => '[DEMO] Precision Aluminum Monitor Light Bar',
                'ratingAverage' => null,
                'ratingCount' => null,
            ],
            [
                'id' => 'demo-prod-3',
                'name' => '[DEMO] Commuter Weatherproof Tech Sling 8L',
                'slug' => 'demo-commuter-weatherproof-tech-sling-8l',
                'sku' => 'DEMO-EDC-003',
                'url' => null,
                'categoryName' => 'Everyday Carry',
                'categorySlug' => 'everyday-carry',
                'sellerName' => 'XHopy Official',
                'unitPrice' => 3400.00,
                'priceFormatted' => '₱3,400.00',
                'originalPrice' => null,
                'originalPriceFormatted' => null,
                'discountLabel' => null,
                'currency' => 'PHP',
                'inStock' => false,
                'stockLabel' => 'Out of Stock',
                'imageUrl' => null,
                'imageAlt' => '[DEMO] Commuter Weatherproof Tech Sling 8L in crimson and black',
                'ratingAverage' => null,
                'ratingCount' => null,
            ],
            [
                'id' => 'demo-prod-4',
                'name' => '[DEMO] Compact Balanced Desktop DAC & Amp',
                'slug' => 'demo-compact-balanced-desktop-dac-amp',
                'sku' => 'DEMO-AUD-004',
                'url' => null,
                'categoryName' => 'Audio & Acoustics',
                'categorySlug' => 'audio-acoustics',
                'sellerName' => 'XHopy Official',
                'unitPrice' => 4950.00,
                'priceFormatted' => '₱4,950.00',
                'originalPrice' => null,
                'originalPriceFormatted' => null,
                'discountLabel' => null,
                'currency' => 'PHP',
                'inStock' => true,
                'stockLabel' => 'In Stock',
                'imageUrl' => null,
                'imageAlt' => '[DEMO] Compact Balanced Desktop DAC & Amp',
                'ratingAverage' => null,
                'ratingCount' => null,
            ],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $products
     * @return list<array<string, mixed>>
     */
    private function filterDemoProducts(array $products, string $searchQuery, ?string $selectedCategory): array
    {
        return array_values(array_filter(
            $products,
            function (array $product) use ($searchQuery, $selectedCategory): bool {
                if ($selectedCategory !== null && $selectedCategory !== '' && ($product['categorySlug'] ?? null) !== $selectedCategory) {
                    return false;
                }

                if ($searchQuery !== '') {
                    $haystack = mb_strtolower(
                        ((string) ($product['name'] ?? '')).' '.((string) ($product['sku'] ?? '')).' '.((string) ($product['categoryName'] ?? ''))
                    );

                    return str_contains($haystack, mb_strtolower($searchQuery));
                }

                return true;
            },
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function demoDailyInterest(): array
    {
        return [
            'featured' => [
                'id' => 'demo-editorial-1',
                'tag' => '[DEMO] Workspace Spotlight',
                'title' => 'Designing a distraction-free desk for deep focus',
                'summary' => 'Explore balanced acoustic gear, glare-free desk illumination, and modular organizers crafted for long engineering sessions.',
                'ctaLabel' => 'View Workspace Collection',
                'href' => '/?preview_demo=1&category=workspace-desk#top-products-heading',
                'imageUrl' => null,
                'imageAlt' => null,
            ],
            'tiles' => [
                [
                    'id' => 'demo-tile-1',
                    'tag' => '[DEMO] Audio Guide',
                    'title' => 'Closed-back vs. open-back studio monitors',
                    'summary' => 'Compare isolation, soundstage, and impedance pairing for home and office setups.',
                    'ctaLabel' => 'Browse Audio & Acoustics',
                    'href' => '/?preview_demo=1&category=audio-acoustics#top-products-heading',
                ],
                [
                    'id' => 'demo-tile-2',
                    'tag' => '[DEMO] Commute Gear',
                    'title' => 'Weatherproof organization for rainy-season travel',
                    'summary' => 'Keep laptops, cables, and daily essentials dry with seam-sealed compartments.',
                    'ctaLabel' => 'Explore Everyday Carry',
                    'href' => '/?preview_demo=1&category=everyday-carry#top-products-heading',
                ],
            ],
        ];
    }
}
