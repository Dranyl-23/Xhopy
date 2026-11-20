<?php

namespace Database\Seeders;

use App\Domain\Catalog\Actions\ManageProductImagesAction;
use App\Domain\Catalog\Actions\SaveProductAction;
use App\Domain\Catalog\Enums\CampaignPlacement;
use App\Domain\Catalog\Enums\ProductStatus;
use App\Domain\Catalog\Models\Brand;
use App\Domain\Catalog\Models\CampaignContent;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Identity\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;

class CatalogDemoSeeder extends Seeder
{
    public function run(
        SaveProductAction $saveProduct,
        ManageProductImagesAction $manageImages,
    ): void {
        if (app()->isProduction()) {
            return;
        }

        // Non-production demo admin user only (clearly labeled, never for production)
        $demoAdmin = User::query()->updateOrCreate(
            ['email' => 'demo-catalog-admin@xhopy.local'],
            [
                'name' => '[DEMO] Catalog Manager',
                'password' => Hash::make('DemoCatalog!2026'),
                'email_verified_at' => now(),
            ],
        );
        $demoAdmin->forceFill(['role' => UserRole::Admin])->save();

        $brandXhopy = Brand::query()->updateOrCreate(
            ['slug' => 'xhopy-studio'],
            [
                'name' => 'XHopy Studio',
                'description' => '[DEMO] Flagship in-house essentials designed for ergonomics and audio.',
                'is_active' => true,
            ],
        );

        $brandLumen = Brand::query()->updateOrCreate(
            ['slug' => 'lumengrid'],
            [
                'name' => 'LumenGrid',
                'description' => '[DEMO] Precision lighting and anodized desk accessories.',
                'is_active' => true,
            ],
        );

        $brandNomad = Brand::query()->updateOrCreate(
            ['slug' => 'nomad-carry'],
            [
                'name' => 'Nomad Carry',
                'description' => '[DEMO] Weatherproof commuter packs and modular pouches.',
                'is_active' => true,
            ],
        );

        $catAudio = Category::query()->updateOrCreate(
            ['slug' => 'audio-acoustics'],
            [
                'name' => '[DEMO] Audio & Acoustics',
                'description' => 'Studio headphones, DACs, and desktop acoustic monitors.',
                'sort_order' => 1,
                'is_active' => true,
            ],
        );

        $catWorkspace = Category::query()->updateOrCreate(
            ['slug' => 'workspace-desk'],
            [
                'name' => '[DEMO] Workspace & Desk',
                'description' => 'Ergonomic monitor lighting, stands, and typing instruments.',
                'sort_order' => 2,
                'is_active' => true,
            ],
        );

        $catCarry = Category::query()->updateOrCreate(
            ['slug' => 'everyday-carry'],
            [
                'name' => '[DEMO] Everyday Carry',
                'description' => 'Weatherproof slings, laptop sleeves, and modular organizers.',
                'sort_order' => 3,
                'is_active' => true,
            ],
        );

        $catHome = Category::query()->updateOrCreate(
            ['slug' => 'home-living'],
            [
                'name' => '[DEMO] Home Living',
                'description' => 'Thermal drinkware and calm bedside utilities.',
                'sort_order' => 4,
                'is_active' => true,
            ],
        );

        $productsSpec = [
            [
                'category_id' => $catAudio->id,
                'brand_id' => $brandXhopy->id,
                'name' => '[DEMO] XHopy Reference Studio Headphones',
                'slug' => 'demo-xhopy-reference-studio-headphones',
                'sku' => 'DEMO-AUD-001',
                'short_description' => 'Closed-back planar headphones tuned for mixing accuracy and all-day comfort.',
                'description' => '[DEMO] Engineered with custom 45mm dynamic drivers, memory-foam protein earpads, and braided detachable OFC cabling.',
                'status' => ProductStatus::Active,
                'is_featured' => true,
                'base_price' => 6490.00,
                'compare_at_price' => 7490.00,
                'stock_quantity' => 18,
                'specifications' => [
                    'Driver Size' => '45mm Dynamic Planar',
                    'Impedance' => '38 Ohms',
                    'Connector' => '3.5mm + 6.35mm Adapter',
                    'Weight' => '265g',
                ],
                'variants' => [
                    [
                        'title' => 'Matte Charcoal',
                        'sku' => 'DEMO-AUD-001-CHR',
                        'price' => 6490.00,
                        'compare_at_price' => 7490.00,
                        'stock_quantity' => 12,
                        'options' => ['Color' => 'Matte Charcoal'],
                        'is_default' => true,
                        'is_active' => true,
                    ],
                    [
                        'title' => 'Deep Crimson',
                        'sku' => 'DEMO-AUD-001-CRM',
                        'price' => 6890.00,
                        'compare_at_price' => 7490.00,
                        'stock_quantity' => 6,
                        'options' => ['Color' => 'Deep Crimson'],
                        'is_default' => false,
                        'is_active' => true,
                    ],
                ],
                'swatch' => [180, 0, 23],
            ],
            [
                'category_id' => $catWorkspace->id,
                'brand_id' => $brandLumen->id,
                'name' => '[DEMO] Precision Aluminum Monitor Light Bar',
                'slug' => 'demo-precision-aluminum-monitor-light-bar',
                'sku' => 'DEMO-WRK-002',
                'short_description' => 'Asymmetric optics illuminate your desk without reflecting off matte or glossy displays.',
                'description' => '[DEMO] CNC-milled aluminum housing with wireless rotary dimmer dial and 2700K–6500K stepless color temperature.',
                'status' => ProductStatus::Active,
                'is_featured' => true,
                'base_price' => 2850.00,
                'compare_at_price' => null,
                'stock_quantity' => 22,
                'specifications' => [
                    'Color Rendering' => 'CRI >= 95',
                    'Power Input' => 'USB-C 5V / 1.5A',
                    'Clamp Range' => '1cm – 5cm Monitors',
                ],
                'variants' => [],
                'swatch' => [34, 38, 45],
            ],
            [
                'category_id' => $catCarry->id,
                'brand_id' => $brandNomad->id,
                'name' => '[DEMO] Commuter Weatherproof Tech Sling 8L',
                'slug' => 'demo-commuter-weatherproof-tech-sling-8l',
                'sku' => 'DEMO-EDC-003',
                'short_description' => 'Water-resistant ballistic nylon sling with suspended 11-inch tablet sleeve.',
                'description' => '[DEMO] Built with AquaGuard zippers, magnetic quick-release buckle, and high-visibility ripstop interior liner.',
                'status' => ProductStatus::Active,
                'is_featured' => true,
                'base_price' => 3400.00,
                'compare_at_price' => null,
                'stock_quantity' => 9,
                'specifications' => [
                    'Capacity' => '8 Liters',
                    'Shell Fabric' => '840D Ballistic Nylon',
                    'Zippers' => 'YKK AquaGuard',
                ],
                'variants' => [
                    [
                        'title' => 'Stealth Black / 8L',
                        'sku' => 'DEMO-EDC-003-BLK',
                        'price' => 3400.00,
                        'compare_at_price' => null,
                        'stock_quantity' => 9,
                        'options' => ['Color' => 'Stealth Black', 'Size' => '8L'],
                        'is_default' => true,
                        'is_active' => true,
                    ],
                    [
                        'title' => 'Crimson Trim / 8L',
                        'sku' => 'DEMO-EDC-003-CRM',
                        'price' => 3550.00,
                        'compare_at_price' => null,
                        'stock_quantity' => 0,
                        'options' => ['Color' => 'Crimson Trim', 'Size' => '8L'],
                        'is_default' => false,
                        'is_active' => true,
                    ],
                ],
                'swatch' => [146, 0, 19],
            ],
            [
                'category_id' => $catAudio->id,
                'brand_id' => $brandXhopy->id,
                'name' => '[DEMO] Compact Balanced Desktop DAC & Amp',
                'slug' => 'demo-compact-balanced-desktop-dac-amp',
                'sku' => 'DEMO-AUD-004',
                'short_description' => 'Low-noise balanced headphone amplifier with 4.4mm Pentaconn & 6.35mm outputs.',
                'description' => '[DEMO] Dual-mono desktop stage engineered for ultra-low output impedance and transparent vocal clarity.',
                'status' => ProductStatus::Active,
                'is_featured' => true,
                'base_price' => 4950.00,
                'compare_at_price' => 5490.00,
                'stock_quantity' => 14,
                'specifications' => [
                    'Outputs' => '4.4mm Balanced + 6.35mm SE',
                    'Sample Rate' => 'Up to 32-bit / 384kHz PCM',
                    'Chassis' => 'Bead-blasted Aluminum',
                ],
                'variants' => [],
                'swatch' => [65, 70, 82],
            ],
            [
                'category_id' => $catWorkspace->id,
                'brand_id' => $brandXhopy->id,
                'name' => '[DEMO] Tactile 75% Low-Profile Mechanical Keyboard',
                'slug' => 'demo-tactile-75-low-profile-mechanical-keyboard',
                'sku' => 'DEMO-WRK-005',
                'short_description' => 'Gasket-mounted hot-swappable aluminum 75% layout with quiet tactile switches.',
                'description' => '[DEMO] Wired USB-C + tri-mode wireless support with PBT keycaps and acoustic dampening foam.',
                'status' => ProductStatus::Active,
                'is_featured' => false,
                'base_price' => 5290.00,
                'compare_at_price' => null,
                'stock_quantity' => 11,
                'specifications' => [
                    'Layout' => '75% (84 Keys)',
                    'Mounting' => 'Poron Gasket Mount',
                    'Keycaps' => 'Double-shot PBT',
                ],
                'variants' => [],
                'swatch' => [48, 52, 60],
            ],
            [
                'category_id' => $catHome->id,
                'brand_id' => $brandLumen->id,
                'name' => '[DEMO] Double-Wall Ceramic Desk Carafe 600ml',
                'slug' => 'demo-double-wall-ceramic-desk-carafe-600ml',
                'sku' => 'DEMO-HOM-006',
                'short_description' => 'Ceramic-lined vacuum carafe keeps coffee hot for 6 hours with zero metallic aftertaste.',
                'description' => '[DEMO] Non-slip silicone base and 360-degree pour-safe lid engineered around keyboard workspaces.',
                'status' => ProductStatus::Active,
                'is_featured' => false,
                'base_price' => 1490.00,
                'compare_at_price' => 1790.00,
                'stock_quantity' => 30,
                'specifications' => [
                    'Volume' => '600ml',
                    'Interior Coating' => 'Food-grade Ceramic',
                ],
                'variants' => [],
                'swatch' => [120, 20, 32],
            ],
        ];

        foreach ($productsSpec as $spec) {
            $existing = Product::query()->withTrashed()->where('sku', $spec['sku'])->first();
            $swatch = $spec['swatch'];
            unset($spec['swatch']);

            $variants = $spec['variants'];
            unset($spec['variants']);

            $product = $saveProduct->execute($existing, $spec, $variants);

            if (! $product->images()->exists()) {
                $this->seedSamplePng($product, $manageImages, $swatch);
            }
        }

        CampaignContent::query()->updateOrCreate(
            ['placement' => CampaignPlacement::Announcement->value, 'sort_order' => 0],
            [
                'tag' => 'Announcement',
                'title' => '[DEMO] Free standard delivery on verified catalog orders within Metro Manila.',
                'summary' => null,
                'cta_label' => 'Browse Catalog',
                'cta_href' => '/catalog',
                'category_id' => null,
                'is_active' => true,
            ],
        );

        CampaignContent::query()->updateOrCreate(
            ['placement' => CampaignPlacement::DailyInterestFeatured->value, 'sort_order' => 0],
            [
                'tag' => '[DEMO] Workspace Spotlight',
                'title' => 'Designing a distraction-free desk for deep focus',
                'summary' => 'Explore balanced acoustic gear, glare-free desk illumination, and modular organizers crafted for long engineering sessions.',
                'cta_label' => 'View Workspace Collection',
                'cta_href' => '/catalog?category=workspace-desk',
                'category_id' => $catWorkspace->id,
                'is_active' => true,
            ],
        );

        CampaignContent::query()->updateOrCreate(
            ['placement' => CampaignPlacement::DailyInterestTile->value, 'sort_order' => 1],
            [
                'tag' => '[DEMO] Audio Guide',
                'title' => 'Closed-back vs. open-back studio monitors',
                'summary' => 'Compare acoustic isolation, soundstage, and impedance pairing for home studios.',
                'cta_label' => 'Browse Audio & Acoustics',
                'cta_href' => '/catalog?category=audio-acoustics',
                'category_id' => $catAudio->id,
                'is_active' => true,
            ],
        );

        CampaignContent::query()->updateOrCreate(
            ['placement' => CampaignPlacement::DailyInterestTile->value, 'sort_order' => 2],
            [
                'tag' => '[DEMO] Commute Gear',
                'title' => 'Weatherproof organization for rainy-season travel',
                'summary' => 'Keep laptops, cables, and daily essentials dry with AquaGuard compartments.',
                'cta_label' => 'Explore Everyday Carry',
                'cta_href' => '/catalog?category=everyday-carry',
                'category_id' => $catCarry->id,
                'is_active' => true,
            ],
        );
    }

    /**
     * @param  array{0: int<0, 255>, 1: int<0, 255>, 2: int<0, 255>}  $rgb
     */
    private function seedSamplePng(
        Product $product,
        ManageProductImagesAction $manageImages,
        array $rgb,
    ): void {
        if (! function_exists('imagecreatetruecolor')) {
            return;
        }

        $canvas = imagecreatetruecolor(640, 640);
        if ($canvas === false) {
            return;
        }

        $r = max(0, min(255, $rgb[0]));
        $g = max(0, min(255, $rgb[1]));
        $b = max(0, min(255, $rgb[2]));
        $accentR = max(0, min(255, $r + 28));
        $accentG = max(0, min(255, $g + 28));
        $accentB = max(0, min(255, $b + 28));

        $bg = imagecolorallocate($canvas, $r, $g, $b);
        $accent = imagecolorallocate($canvas, $accentR, $accentG, $accentB);
        if ($bg !== false) {
            imagefill($canvas, 0, 0, $bg);
        }
        if ($accent !== false) {
            imagefilledellipse($canvas, 320, 320, 260, 260, $accent);
        }

        $tempPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'xhopy_seed_'.$product->id.'.png';
        imagepng($canvas, $tempPath, 6);

        $uploaded = new UploadedFile($tempPath, 'product-'.$product->id.'.png', 'image/png', null, true);
        $manageImages->upload(
            product: $product,
            file: $uploaded,
            altText: $product->name.' studio product preview',
            makePrimary: true,
        );

        @unlink($tempPath);
    }
}
