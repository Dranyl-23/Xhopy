import { execFileSync, spawn, type ChildProcess } from 'node:child_process';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import process from 'node:process';
import { expect, test } from '@playwright/test';

const backendRoot = path.resolve(import.meta.dirname, '../../backend');
const artifactsDir =
    'C:/Users/Alfie Lynard/.gemini/antigravity/brain/06b53266-074a-40bf-95e3-5edb0ba69275';
const isolatedDbPath = path.join(
    backendRoot,
    'storage/framework/playwright-mv4-e2e.sqlite',
);
const mv4Port = process.env.PLAYWRIGHT_MV4_PORT ?? '18088';
const mv4BaseUrl = `http://127.0.0.1:${mv4Port}`;

const herdPhp = path.join(os.homedir(), '.config/herd/bin/php85/php.exe');
const fallbackWinPhp = 'C:\\php85\\php.exe';
const useHerdPhp = process.platform === 'win32' && fs.existsSync(herdPhp);
const useFallbackWinPhp =
    !useHerdPhp &&
    process.platform === 'win32' &&
    fs.existsSync(fallbackWinPhp);

const phpCmd = useHerdPhp
    ? herdPhp
    : useFallbackWinPhp
      ? fallbackWinPhp
      : 'php';

const phpExtensions = useFallbackWinPhp
    ? [
          '-d',
          'extension=C:\\php85\\ext\\php_pdo_sqlite.dll',
          '-d',
          'extension=C:\\php85\\ext\\php_sodium.dll',
          '-d',
          'extension=C:\\php85\\ext\\php_gd.dll',
      ]
    : [];

let serverProcess: ChildProcess | null = null;

async function waitForServer(url: string): Promise<void> {
    const deadline = Date.now() + 20_000;
    while (Date.now() < deadline) {
        try {
            const res = await fetch(url);
            if (res.ok) {
                return;
            }
        } catch {
            // Retry until server binds
        }
        await new Promise((r) => setTimeout(r, 250));
    }
    throw new Error(`Timed out waiting for isolated server at ${url}`);
}

test.describe
    .serial('Phase MV-4 Storefront Attribution, Public Stores & Multi-Seller Cart Grouping', () => {
    test.beforeAll(async () => {
        if (fs.existsSync(isolatedDbPath)) {
            fs.unlinkSync(isolatedDbPath);
        }
        fs.writeFileSync(isolatedDbPath, '');

        const serverEnv = {
            ...process.env,
            APP_ENV: 'local',
            DB_CONNECTION: 'sqlite',
            DB_DATABASE: isolatedDbPath,
            APP_URL: mv4BaseUrl,
            MAIL_MAILER: 'log',
            QUEUE_CONNECTION: 'sync',
            AUTH_REQUIRE_PRIVILEGED_MFA: 'false',
        };

        execFileSync(
            phpCmd,
            [...phpExtensions, 'artisan', 'migrate:fresh', '--force'],
            {
                cwd: backendRoot,
                env: serverEnv,
                stdio: 'pipe',
            },
        );

        const seedPhp = `
$buyer = App\\Models\\User::factory()->create([
    'name' => 'MV4 Buyer',
    'email' => 'buyer.mv4@gmail.com',
    'password' => 'Mv4!Pass2026',
]);
App\\Domain\\Customer\\Models\\CustomerAddress::factory()->defaultShipping()->create([
    'user_id' => $buyer->id,
    'label' => 'Home Office',
    'recipient_name' => 'MV4 Buyer',
    'phone' => '+63 917 555 0144',
    'address_line_1' => '88 Ayala Avenue',
    'barangay' => 'Bel-Air',
    'city' => 'Makati City',
    'province' => 'Metro Manila',
    'postal_code' => '1209',
]);
$catTech = App\\Domain\\Catalog\\Models\\Category::factory()->create([
    'name' => 'Workspace Tech',
    'slug' => 'workspace-tech',
    'is_active' => true,
]);
$catCoffee = App\\Domain\\Catalog\\Models\\Category::factory()->create([
    'name' => 'Artisan Pantry',
    'slug' => 'artisan-pantry',
    'is_active' => true,
]);
$admin = App\\Models\\User::factory()->admin()->create([
    'name' => 'Admin MV4',
    'email' => 'admin.mv4@gmail.com',
    'password' => 'Mv4!Pass2026',
]);
$officialStore = App\\Domain\\Seller\\Models\\Store::query()->updateOrCreate(
    ['slug' => 'xhopy-official'],
    [
        'owner_user_id' => $admin->id,
        'name' => 'XHopy Official',
        'description' => 'Official flagship essentials directly fulfilled by XHopy.',
        'status' => App\\Domain\\Seller\\Enums\\StoreStatus::Active,
        'is_official' => true,
    ],
);
$coffeeOwner = App\\Models\\User::factory()->seller()->create([
    'name' => 'Roast Owner',
    'email' => 'coffee.mv4@gmail.com',
    'password' => 'Mv4!Pass2026',
]);
$coffeeStore = App\\Domain\\Seller\\Models\\Store::query()->create([
    'owner_user_id' => $coffeeOwner->id,
    'name' => 'Manila Roast Coffee',
    'slug' => 'manila-roast-coffee',
    'description' => 'Single-origin Philippine highland coffee roasted daily in small batches.',
    'status' => App\\Domain\\Seller\\Enums\\StoreStatus::Active,
    'is_official' => false,
]);
$suspendedOwner = App\\Models\\User::factory()->seller()->create([
    'name' => 'Suspended Owner',
    'email' => 'suspended.mv4@gmail.com',
    'password' => 'Mv4!Pass2026',
]);
$suspendedStore = App\\Domain\\Seller\\Models\\Store::query()->create([
    'owner_user_id' => $suspendedOwner->id,
    'name' => 'Hidden Suspended Shop',
    'slug' => 'hidden-suspended-shop',
    'description' => 'Should never appear on public storefronts.',
    'status' => App\\Domain\\Seller\\Enums\\StoreStatus::Suspended,
    'is_official' => false,
]);
App\\Domain\\Catalog\\Models\\Product::factory()->create([
    'category_id' => $catTech->id,
    'store_id' => $officialStore->id,
    'seller_name' => $officialStore->name,
    'name' => 'XHopy Mechanical Keyboard',
    'slug' => 'xhopy-mechanical-keyboard',
    'sku' => 'MV4-KEY-001',
    'base_price' => '3200.00',
    'stock_quantity' => 12,
    'status' => App\\Domain\\Catalog\\Enums\\ProductStatus::Active,
]);
App\\Domain\\Catalog\\Models\\Product::factory()->create([
    'category_id' => $catCoffee->id,
    'store_id' => $coffeeStore->id,
    'seller_name' => $coffeeStore->name,
    'name' => 'Single Origin Arabica Beans',
    'slug' => 'single-origin-arabica-beans',
    'sku' => 'MV4-COF-001',
    'base_price' => '650.00',
    'stock_quantity' => 20,
    'status' => App\\Domain\\Catalog\\Enums\\ProductStatus::Active,
]);
App\\Domain\\Catalog\\Models\\Product::factory()->create([
    'category_id' => $catTech->id,
    'store_id' => $suspendedStore->id,
    'seller_name' => $suspendedStore->name,
    'name' => 'Ghost Suspended Keyboard',
    'slug' => 'ghost-suspended-keyboard',
    'sku' => 'MV4-SUS-001',
    'base_price' => '1999.00',
    'stock_quantity' => 5,
    'status' => App\\Domain\\Catalog\\Enums\\ProductStatus::Active,
]);
`;

        execFileSync(
            phpCmd,
            [...phpExtensions, 'artisan', 'tinker', '--execute', seedPhp],
            {
                cwd: backendRoot,
                env: serverEnv,
                stdio: 'pipe',
            },
        );

        serverProcess = spawn(
            phpCmd,
            [
                ...phpExtensions,
                '-S',
                `127.0.0.1:${mv4Port}`,
                '../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php',
            ],
            {
                cwd: path.join(backendRoot, 'public'),
                env: serverEnv,
                stdio: 'ignore',
            },
        );

        await waitForServer(mv4BaseUrl);
    });

    test.afterAll(() => {
        if (serverProcess) {
            serverProcess.kill();
            serverProcess = null;
        }
        if (fs.existsSync(isolatedDbPath)) {
            try {
                fs.unlinkSync(isolatedDbPath);
            } catch {
                // Ignore transient cleanup lock
            }
        }
    });

    test('browses public stores, follows store attribution links, groups multi-seller cart & checkout, and preserves mobile responsiveness', async ({
        page,
    }) => {
        test.setTimeout(90_000);
        const pageErrors: string[] = [];
        page.on('pageerror', (err) => pageErrors.push(err.message));

        await page.setViewportSize({ width: 1280, height: 800 });

        // 1. Sign in as buyer so checkout flow can also be inspected cleanly.
        await page.goto(`${mv4BaseUrl}/login`, { waitUntil: 'networkidle' });
        await page.getByLabel('Email address').fill('buyer.mv4@gmail.com');
        await page
            .getByRole('textbox', { name: 'Password' })
            .fill('Mv4!Pass2026');
        await page.getByRole('button', { name: 'Log in' }).click();
        await expect(page).not.toHaveURL(/\/login/);
        await page.waitForLoadState('networkidle');

        // 2. Visit Verified Stores Directory (/stores).
        await page.goto(`${mv4BaseUrl}/stores`, { waitUntil: 'networkidle' });
        await expect(
            page.getByRole('heading', { name: 'Verified Stores' }),
        ).toBeVisible();
        await expect(
            page.getByRole('link', { name: 'XHopy Official', exact: true }),
        ).toBeVisible();
        await expect(
            page.getByRole('link', {
                name: 'Manila Roast Coffee',
                exact: true,
            }),
        ).toBeVisible();
        await expect(page.getByText('Hidden Suspended Shop')).toHaveCount(0);

        await page.screenshot({
            path: path.join(artifactsDir, 'mv4_stores_directory.png'),
            fullPage: true,
        });

        // Verify suspended store returns 404 directly.
        const suspendedRes = await page.request.get(
            `${mv4BaseUrl}/stores/hidden-suspended-shop`,
        );
        expect(suspendedRes.status()).toBe(404);

        // 3. Visit Manila Roast Coffee public storefront & add Single Origin Arabica Beans to cart.
        await page.goto(`${mv4BaseUrl}/stores/manila-roast-coffee`, {
            waitUntil: 'networkidle',
        });
        await expect(
            page.getByRole('heading', {
                name: 'Manila Roast Coffee',
                level: 1,
            }),
        ).toBeVisible();
        await expect(
            page.getByRole('link', {
                name: 'Single Origin Arabica Beans',
                exact: true,
            }),
        ).toBeVisible();
        await expect(page.getByText('XHopy Mechanical Keyboard')).toHaveCount(
            0,
        );

        await page
            .getByRole('button', {
                name: 'Add Single Origin Arabica Beans to cart',
            })
            .click();
        await page.waitForLoadState('networkidle');

        // 4. Filter catalog by store & follow ProductDetail store attribution link.
        await page.goto(`${mv4BaseUrl}/catalog?store=xhopy-official`, {
            waitUntil: 'networkidle',
        });
        await expect(
            page.getByRole('link', {
                name: 'XHopy Mechanical Keyboard',
                exact: true,
            }),
        ).toBeVisible();
        await expect(page.getByText('Single Origin Arabica Beans')).toHaveCount(
            0,
        );

        await page
            .getByRole('link', {
                name: 'XHopy Mechanical Keyboard',
                exact: true,
            })
            .click();
        await page.waitForLoadState('networkidle');

        const officialLink = page
            .getByRole('link', { name: 'XHopy Official', exact: true })
            .first();
        await expect(officialLink).toBeVisible();
        await expect(officialLink).toHaveAttribute(
            'href',
            /\/stores\/xhopy-official$/,
        );

        await page
            .getByRole('button', { name: /add to cart/i })
            .first()
            .click();
        await page.waitForLoadState('networkidle');

        // 5. Inspect Multi-Seller Cart (/cart) grouped by store.
        await page.goto(`${mv4BaseUrl}/cart`, { waitUntil: 'networkidle' });
        await expect(page.getByText('2 stores')).toBeVisible();
        await expect(
            page.getByLabel('Items from XHopy Official'),
        ).toBeVisible();
        await expect(
            page.getByLabel('Items from Manila Roast Coffee'),
        ).toBeVisible();
        await expect(page.getByText('Store Subtotal: ₱3,200.00')).toBeVisible();
        await expect(page.getByText('Store Subtotal: ₱650.00')).toBeVisible();
        await expect(page.getByText('₱3,850.00').first()).toBeVisible();

        await page.screenshot({
            path: path.join(artifactsDir, 'mv4_multivendor_cart.png'),
            fullPage: true,
        });

        // 6. Proceed to Checkout (/checkout) & verify store-grouped summary.
        await page.getByRole('link', { name: /proceed to checkout/i }).click();
        await page.waitForLoadState('networkidle');
        await expect(page).toHaveURL(/\/checkout/);
        await expect(page.getByText('XHopy Official').first()).toBeVisible();
        await expect(
            page.getByText('Manila Roast Coffee').first(),
        ).toBeVisible();
        await expect(page.getByText('₱3,850.00').first()).toBeVisible();

        await page.screenshot({
            path: path.join(artifactsDir, 'mv4_multivendor_checkout.png'),
            fullPage: true,
        });

        // 7. Mobile viewport responsiveness check (375x812) with zero horizontal overflow.
        await page.setViewportSize({ width: 375, height: 812 });
        await page.goto(`${mv4BaseUrl}/cart`, { waitUntil: 'networkidle' });
        const cartOverflow = await page.evaluate(
            () =>
                document.documentElement.scrollWidth -
                document.documentElement.clientWidth,
        );
        expect(cartOverflow).toBeLessThanOrEqual(1);

        await page.screenshot({
            path: path.join(artifactsDir, 'mv4_mobile_cart.png'),
            fullPage: true,
        });

        await page.goto(`${mv4BaseUrl}/stores`, { waitUntil: 'networkidle' });
        const storesOverflow = await page.evaluate(
            () =>
                document.documentElement.scrollWidth -
                document.documentElement.clientWidth,
        );
        expect(storesOverflow).toBeLessThanOrEqual(1);

        expect(pageErrors).toEqual([]);
    });
});
