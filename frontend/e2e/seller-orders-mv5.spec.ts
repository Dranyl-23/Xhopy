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
    'storage/framework/playwright-mv5-e2e.sqlite',
);
const mv5Port = process.env.PLAYWRIGHT_MV5_PORT ?? '18089';
const mv5BaseUrl = `http://127.0.0.1:${mv5Port}`;

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
            // Wait for server socket
        }
        await new Promise((r) => setTimeout(r, 250));
    }
    throw new Error(`Timed out waiting for isolated server at ${url}`);
}

test.describe
    .serial('Phase MV-5 Multi-Vendor Order Splitting & Independent Seller Fulfillment', () => {
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
            APP_URL: mv5BaseUrl,
            MAIL_MAILER: 'log',
            QUEUE_CONNECTION: 'sync',
            PAYMONGO_SECRET_KEY: '',
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
    'name' => 'MV5 Buyer',
    'email' => 'buyer.mv5@gmail.com',
    'password' => 'Mv5!Pass2026',
]);
App\\Domain\\Customer\\Models\\CustomerAddress::factory()->defaultShipping()->create([
    'user_id' => $buyer->id,
    'label' => 'Studio Loft',
    'recipient_name' => 'MV5 Buyer',
    'phone' => '+63 917 555 0155',
    'address_line_1' => '101 Legazpi Street',
    'barangay' => 'San Lorenzo',
    'city' => 'Makati City',
    'province' => 'Metro Manila',
    'postal_code' => '1229',
]);
$admin = App\\Models\\User::factory()->admin()->create([
    'name' => 'Super Admin MV5',
    'email' => 'admin.mv5@gmail.com',
    'password' => 'Mv5!Pass2026',
]);
$category = App\\Domain\\Catalog\\Models\\Category::factory()->create([
    'name' => 'Creator Essentials',
    'slug' => 'creator-essentials',
    'is_active' => true,
]);
$makeSeller = function (string $email, string $name, string $storeName, string $slug, string $productName, string $productSlug, string $sku, string $price) use ($category): void {
    $u = App\\Models\\User::factory()->seller()->create(['name' => $name, 'email' => $email, 'password' => 'Mv5!Pass2026']);
    $s = App\\Domain\\Seller\\Models\\Store::query()->create(['owner_user_id' => $u->id, 'name' => $storeName, 'slug' => $slug, 'status' => 'active', 'is_official' => false]);
    App\\Domain\\Seller\\Models\\StoreMembership::query()->create(['store_id' => $s->id, 'user_id' => $u->id, 'role' => 'owner']);
    App\\Domain\\Seller\\Models\\SellerProfile::query()->create(['user_id' => $u->id, 'store_id' => $s->id, 'business_name' => $storeName . ' Corp', 'business_type' => 'sole_proprietor', 'contact_phone' => '+63 917 555 0100', 'business_address' => 'Makati City', 'status' => 'approved', 'submitted_at' => now(), 'reviewed_at' => now(), 'approved_at' => now()]);
    App\\Domain\\Catalog\\Models\\Product::factory()->create([
        'category_id' => $category->id,
        'store_id' => $s->id,
        'seller_name' => $storeName,
        'name' => $productName,
        'slug' => $productSlug,
        'sku' => $sku,
        'base_price' => $price,
        'stock_quantity' => 10,
        'status' => App\\Domain\\Catalog\\Enums\\ProductStatus::Active,
    ]);
};
$makeSeller('alpha.mv5@gmail.com', 'Alpha Owner', 'Alpha Coffee House', 'alpha-coffee-house', 'Arabica Pour-Over Kit', 'arabica-pour-over-kit', 'MV5-ALP-01', '1500.00');
$makeSeller('beta.mv5@gmail.com', 'Beta Owner', 'Beta Audio Works', 'beta-audio-works', 'USB-C DAC Dongle', 'usb-c-dac-dongle', 'MV5-BET-01', '900.00');
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
                `127.0.0.1:${mv5Port}`,
                '../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php',
            ],
            {
                cwd: path.join(backendRoot, 'public'),
                env: serverEnv,
                stdio: 'ignore',
            },
        );

        await waitForServer(mv5BaseUrl);
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

    test('splits mixed-seller checkout, empowers independent seller fulfillment with tracking, updates customer rolled-up progress, and supports admin suborder oversight', async ({
        browser,
    }) => {
        test.setTimeout(120_000);
        const pageErrors: string[] = [];

        // 1. Customer places mixed-seller order & confirms payment in local sandbox.
        const buyerCtx = await browser.newContext({
            viewport: { width: 1280, height: 800 },
        });
        const buyerPage = await buyerCtx.newPage();
        buyerPage.on('pageerror', (err) => pageErrors.push(err.message));

        await buyerPage.goto(`${mv5BaseUrl}/login`, {
            waitUntil: 'networkidle',
        });
        await buyerPage.getByLabel('Email address').fill('buyer.mv5@gmail.com');
        await buyerPage
            .getByRole('textbox', { name: 'Password' })
            .fill('Mv5!Pass2026');
        await buyerPage.getByRole('button', { name: 'Log in' }).click();
        await expect(buyerPage).not.toHaveURL(/\/login/);

        await buyerPage.goto(`${mv5BaseUrl}/stores/alpha-coffee-house`, {
            waitUntil: 'networkidle',
        });
        await buyerPage
            .getByRole('button', { name: 'Add Arabica Pour-Over Kit to cart' })
            .click();
        await buyerPage.waitForLoadState('networkidle');

        await buyerPage.goto(`${mv5BaseUrl}/stores/beta-audio-works`, {
            waitUntil: 'networkidle',
        });
        await buyerPage
            .getByRole('button', { name: 'Add USB-C DAC Dongle to cart' })
            .click();
        await buyerPage.waitForLoadState('networkidle');

        await buyerPage.goto(`${mv5BaseUrl}/checkout`, {
            waitUntil: 'networkidle',
        });
        await expect(buyerPage.getByText('₱2,400.00').first()).toBeVisible();
        await buyerPage
            .getByRole('button', { name: 'Place Order & Pay with PayMongo' })
            .click();

        await expect(buyerPage).toHaveURL(/\/checkout\/sandbox\/\d+/);
        await buyerPage.getByRole('button', { name: 'Simulate Paid' }).click();
        await expect(buyerPage).toHaveURL(/\/checkout\/success\/\d+/);
        await buyerPage.getByRole('link', { name: 'View Order' }).click();
        await expect(buyerPage).toHaveURL(/\/orders\/\d+/);

        const customerOrderUrl = buyerPage.url();
        await expect(
            buyerPage.getByRole('link', { name: 'Alpha Coffee House' }),
        ).toBeVisible();
        await expect(
            buyerPage.getByRole('link', { name: 'Beta Audio Works' }),
        ).toBeVisible();

        // 2. Seller A signs in -> fulfills Alpha Coffee House suborder independently with tracking.
        const sellerCtx = await browser.newContext({
            viewport: { width: 1280, height: 800 },
        });
        const sellerPage = await sellerCtx.newPage();
        sellerPage.on('pageerror', (err) => pageErrors.push(err.message));

        await sellerPage.goto(`${mv5BaseUrl}/login`, {
            waitUntil: 'networkidle',
        });
        await sellerPage
            .getByLabel('Email address')
            .fill('alpha.mv5@gmail.com');
        await sellerPage
            .getByRole('textbox', { name: 'Password' })
            .fill('Mv5!Pass2026');
        await sellerPage.getByRole('button', { name: 'Log in' }).click();
        await expect(sellerPage).toHaveURL(/\/seller\/dashboard/);

        await sellerPage
            .getByRole('link', { name: 'Orders & Fulfillment' })
            .click();
        await expect(sellerPage).toHaveURL(/\/seller\/orders/);
        await expect(
            sellerPage.getByText('XHP-', { exact: false }).first(),
        ).toBeVisible();

        await sellerPage.getByRole('link', { name: 'Manage' }).first().click();
        await expect(sellerPage).toHaveURL(/\/seller\/orders\/\d+/);
        await expect(
            sellerPage.getByText('Arabica Pour-Over Kit'),
        ).toBeVisible();
        await expect(sellerPage.getByText('USB-C DAC Dongle')).toHaveCount(0);

        await sellerPage
            .getByRole('button', { name: 'Update Fulfillment Status' })
            .click();
        await expect(
            sellerPage.getByText('Processing', { exact: true }).first(),
        ).toBeVisible();

        // Next option automatically switches to Shipped -> attach carrier & tracking.
        await sellerPage.getByLabel('Shipping Carrier').fill('LBC Express');
        await sellerPage.getByLabel('Tracking Number').fill('LBC-MV5-777');
        await sellerPage
            .getByRole('button', { name: 'Update Fulfillment Status' })
            .click();
        await expect(sellerPage.getByText('LBC-MV5-777').first()).toBeVisible();

        await sellerPage.screenshot({
            path: path.join(artifactsDir, 'mv5_seller_order_detail.png'),
            fullPage: true,
        });

        // Verify cross-seller IDOR isolation: Seller A cannot view Seller B's suborder.
        const sellerACurrentId = Number(
            sellerPage.url().match(/\/seller\/orders\/(\d+)/)?.[1] ?? '1',
        );
        const siblingId = sellerACurrentId === 1 ? 2 : 1;
        const idorRes = await sellerPage.request.get(
            `${mv5BaseUrl}/seller/orders/${siblingId}`,
        );
        expect(idorRes.status()).toBe(403);

        // Mobile responsiveness check on Seller Orders.
        await sellerPage.setViewportSize({ width: 375, height: 812 });
        await sellerPage.goto(`${mv5BaseUrl}/seller/orders`, {
            waitUntil: 'networkidle',
        });
        const sellerOverflow = await sellerPage.evaluate(
            () =>
                document.documentElement.scrollWidth -
                document.documentElement.clientWidth,
        );
        expect(sellerOverflow).toBeLessThanOrEqual(1);

        await sellerPage.screenshot({
            path: path.join(artifactsDir, 'mv5_mobile_seller_orders.png'),
            fullPage: true,
        });

        await sellerCtx.close();

        // 3. Customer refreshes split order page & sees independent tracking + rolled-up status.
        await buyerPage.goto(customerOrderUrl, { waitUntil: 'networkidle' });
        await expect(buyerPage.getByText('LBC-MV5-777').first()).toBeVisible();
        await expect(
            buyerPage.getByText('Processing', { exact: true }).first(),
        ).toBeVisible();

        await buyerPage.screenshot({
            path: path.join(artifactsDir, 'mv5_customer_split_order.png'),
            fullPage: true,
        });

        await buyerCtx.close();

        // 4. Super Admin signs in -> sees Seller Fulfillment Groups card on Admin Order Detail.
        const adminCtx = await browser.newContext({
            viewport: { width: 1280, height: 800 },
        });
        const adminPage = await adminCtx.newPage();
        adminPage.on('pageerror', (err) => pageErrors.push(err.message));

        await adminPage.goto(`${mv5BaseUrl}/login`, {
            waitUntil: 'networkidle',
        });
        await adminPage.getByLabel('Email address').fill('admin.mv5@gmail.com');
        await adminPage
            .getByRole('textbox', { name: 'Password' })
            .fill('Mv5!Pass2026');
        await adminPage.getByRole('button', { name: 'Log in' }).click();
        await expect(adminPage).not.toHaveURL(/\/login/);
        await adminPage.goto(`${mv5BaseUrl}/admin/orders`, {
            waitUntil: 'networkidle',
        });
        await expect(adminPage.getByText('2 stores').first()).toBeVisible();

        await adminPage.getByRole('link', { name: 'Manage' }).first().click();
        await expect(adminPage).toHaveURL(/\/admin\/orders\/\d+/);
        await expect(
            adminPage.getByText('Seller Fulfillment Groups (Suborders)'),
        ).toBeVisible();
        await expect(adminPage.getByText('LBC-MV5-777').first()).toBeVisible();

        await adminPage.screenshot({
            path: path.join(artifactsDir, 'mv5_admin_suborders.png'),
            fullPage: true,
        });

        await adminCtx.close();

        expect(pageErrors).toEqual([]);
    });
});
