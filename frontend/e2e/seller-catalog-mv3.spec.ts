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
    'storage/framework/playwright-mv3-e2e.sqlite',
);
const mv3Port = process.env.PLAYWRIGHT_MV3_PORT ?? '18087';
const mv3BaseUrl = `http://127.0.0.1:${mv3Port}`;

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

// Valid 1x1 PNG buffer passing finfo + GD re-encode checks cleanly.
const minimalPngBase64 =
    'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

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
    .serial('Phase MV-3 Seller Store Catalog, Moderation, Inventory & IDOR Isolation', () => {
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
            APP_URL: mv3BaseUrl,
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
$admin = App\\Models\\User::factory()->admin()->create(['name' => 'Super Admin MV3', 'email' => 'superadmin.mv3@gmail.com', 'password' => 'Mv3!Pass2026']);
$cat = App\\Domain\\Catalog\\Models\\Category::factory()->create(['name' => 'Audio Hardware', 'slug' => 'audio-hardware', 'is_active' => true]);
$makeSeller = function (string $email, string $name, string $storeName, string $slug): void {
    $u = App\\Models\\User::factory()->seller()->create(['name' => $name, 'email' => $email, 'password' => 'Mv3!Pass2026']);
    $s = App\\Domain\\Seller\\Models\\Store::query()->create(['owner_user_id' => $u->id, 'name' => $storeName, 'slug' => $slug, 'status' => 'active', 'is_official' => false]);
    App\\Domain\\Seller\\Models\\StoreMembership::query()->create(['store_id' => $s->id, 'user_id' => $u->id, 'role' => 'owner']);
    App\\Domain\\Seller\\Models\\SellerProfile::query()->create(['user_id' => $u->id, 'store_id' => $s->id, 'business_name' => $storeName . ' OPC', 'business_type' => 'sole_proprietor', 'contact_phone' => '+63 917 555 0100', 'business_address' => 'Makati City', 'status' => 'approved', 'submitted_at' => now(), 'reviewed_at' => now(), 'approved_at' => now()]);
};
$makeSeller('alpha.mv3@gmail.com', 'Alpha Owner', 'Alpha Craft Store', 'alpha-craft-store');
$makeSeller('beta.mv3@gmail.com', 'Beta Owner', 'Beta Tech Store', 'beta-tech-store');
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
                '-d',
                'upload_max_filesize=8M',
                '-d',
                'post_max_size=10M',
                '-S',
                `127.0.0.1:${mv3Port}`,
                '../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php',
            ],
            {
                cwd: path.join(backendRoot, 'public'),
                env: serverEnv,
                stdio: 'ignore',
            },
        );

        await waitForServer(mv3BaseUrl);
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

    test('seller edits store, submits product for admin moderation, admin rejects then approves live, and cross-seller isolation holds', async ({
        browser,
    }) => {
        test.setTimeout(90_000);
        const errors: string[] = [];

        // 1. Approved Seller A signs in -> updates store profile -> submits product + PNG image
        const alphaCtx = await browser.newContext({
            viewport: { width: 1280, height: 800 },
        });
        const alphaPage = await alphaCtx.newPage();
        alphaPage.on('pageerror', (err) => errors.push(err.message));

        await alphaPage.goto(`${mv3BaseUrl}/login`, {
            waitUntil: 'networkidle',
        });
        await alphaPage.getByLabel('Email address').fill('alpha.mv3@gmail.com');
        await alphaPage
            .getByRole('textbox', { name: 'Password' })
            .fill('Mv3!Pass2026');
        await alphaPage.getByRole('button', { name: 'Log in' }).click();
        await expect(alphaPage).toHaveURL(/\/seller\/dashboard/);

        // Store Profile edit
        await alphaPage
            .getByRole('link', { name: 'Store Profile' })
            .first()
            .click();
        await expect(alphaPage).toHaveURL(/\/seller\/store/);
        await alphaPage
            .getByLabel('Store Display Name')
            .fill('Alpha Sound Studio');
        await alphaPage
            .getByRole('button', { name: 'Save Store Profile' })
            .click();
        await expect(
            alphaPage.getByText('Alpha Sound Studio').first(),
        ).toBeVisible();

        // Create Store Product submitted for moderation review
        await alphaPage.getByRole('link', { name: 'Products' }).first().click();
        await expect(alphaPage).toHaveURL(/\/seller\/products/);
        await alphaPage
            .getByRole('link', { name: /New Product|Add First Product/i })
            .first()
            .click();
        await expect(alphaPage).toHaveURL(/\/seller\/products\/create/);

        await alphaPage
            .getByLabel('Product Name')
            .fill('MV3 Studio Ribbon Microphone');
        await alphaPage.getByLabel('Primary SKU').fill('MV3-RIBBON-001');
        await alphaPage.getByLabel('Base Price (PHP)').fill('5499.00');
        await alphaPage.getByLabel('Stock Quantity').fill('14');
        await alphaPage.locator('#seller-product-status').click();
        await alphaPage.getByRole('option', { name: 'Pending Review' }).click();
        await alphaPage
            .getByRole('button', { name: 'Create Store Product' })
            .click();

        await expect(alphaPage).toHaveURL(/\/seller\/products\/\d+\/edit/);
        const alphaEditUrl = alphaPage.url();
        await expect(
            alphaPage.getByText('Pending Moderation Review'),
        ).toBeVisible();

        // Upload real PNG product image and verify private thumbnail renders
        await alphaPage.locator('#seller-media-file').setInputFiles({
            name: 'ribbon-mic.png',
            mimeType: 'image/png',
            buffer: Buffer.from(minimalPngBase64, 'base64'),
        });
        await alphaPage
            .getByLabel('Accessible Alt Description')
            .fill('MV3 Studio Ribbon Microphone front view');
        await alphaPage.getByRole('button', { name: 'Upload Image' }).click();
        await expect(
            alphaPage.getByRole('img', {
                name: 'MV3 Studio Ribbon Microphone front view',
            }),
        ).toBeVisible();

        if (!process.env.CI && fs.existsSync(artifactsDir)) {
            await alphaPage.screenshot({
                path: path.join(artifactsDir, 'mv3_seller_product_form.png'),
            });
        }

        // Check mobile 375px zero horizontal overflow on Seller Products & Inventory
        await alphaPage.goto(`${mv3BaseUrl}/seller/products`, {
            waitUntil: 'networkidle',
        });
        await alphaPage.setViewportSize({ width: 375, height: 812 });
        const productsMobileOverflow = await alphaPage.evaluate(
            () =>
                document.documentElement.scrollWidth >
                document.documentElement.clientWidth,
        );
        expect(productsMobileOverflow).toBe(false);
        await alphaPage.setViewportSize({ width: 1280, height: 800 });

        if (!process.env.CI && fs.existsSync(artifactsDir)) {
            await alphaPage.screenshot({
                path: path.join(artifactsDir, 'mv3_seller_products.png'),
            });
        }

        // 2. Approved Seller B cannot access Seller A's product URL (403) and sees 0 of Alpha's items
        const betaCtx = await browser.newContext({
            viewport: { width: 1280, height: 800 },
        });
        const betaPage = await betaCtx.newPage();
        betaPage.on('pageerror', (err) => errors.push(err.message));

        await betaPage.goto(`${mv3BaseUrl}/login`, {
            waitUntil: 'networkidle',
        });
        await betaPage.getByLabel('Email address').fill('beta.mv3@gmail.com');
        await betaPage
            .getByRole('textbox', { name: 'Password' })
            .fill('Mv3!Pass2026');
        await betaPage.getByRole('button', { name: 'Log in' }).click();
        await expect(betaPage).toHaveURL(/\/seller\/dashboard/);

        const crossSellerResp = await betaPage.goto(alphaEditUrl, {
            waitUntil: 'networkidle',
        });
        expect(crossSellerResp?.status()).toBe(403);
        await expect(betaPage.getByText('403 Unauthorized')).toBeVisible();

        await betaPage.goto(`${mv3BaseUrl}/seller/products`, {
            waitUntil: 'networkidle',
        });
        await expect(
            betaPage.getByText('MV3 Studio Ribbon Microphone'),
        ).toHaveCount(0);
        await betaCtx.close();

        // 3. Super Admin moderates -> rejects with reason -> Seller A resubmits -> Admin approves live
        const adminCtx = await browser.newContext({
            viewport: { width: 1280, height: 800 },
        });
        const adminPage = await adminCtx.newPage();
        adminPage.on('pageerror', (err) => errors.push(err.message));

        await adminPage.goto(`${mv3BaseUrl}/login`, {
            waitUntil: 'networkidle',
        });
        await adminPage
            .getByLabel('Email address')
            .fill('superadmin.mv3@gmail.com');
        await adminPage
            .getByRole('textbox', { name: 'Password' })
            .fill('Mv3!Pass2026');
        await adminPage.getByRole('button', { name: 'Log in' }).click();
        await expect(adminPage).toHaveURL(/\/admin\/operations/);

        await adminPage.goto(`${mv3BaseUrl}/admin/catalog`, {
            waitUntil: 'networkidle',
        });
        await expect(
            adminPage.getByText('Alpha Sound Studio').first(),
        ).toBeVisible();
        await expect(
            adminPage.getByText('Pending Review').first(),
        ).toBeVisible();

        await adminPage.getByRole('link', { name: 'Edit' }).first().click();
        await expect(adminPage).toHaveURL(
            /\/admin\/catalog\/products\/\d+\/edit/,
        );

        if (!process.env.CI && fs.existsSync(artifactsDir)) {
            await adminPage.screenshot({
                path: path.join(artifactsDir, 'mv3_admin_moderation.png'),
            });
        }

        await adminPage
            .getByLabel('Moderation Feedback / Change Request Reason')
            .fill('Include XLR cable compatibility note.');
        await adminPage
            .getByRole('button', { name: 'Request Changes' })
            .click();
        await expect(
            adminPage.getByText('Changes Requested').first(),
        ).toBeVisible();

        // Seller A sees rejection reason banner, updates, and resubmits
        await alphaPage.goto(alphaEditUrl, { waitUntil: 'networkidle' });
        await expect(
            alphaPage.getByText('Include XLR cable compatibility note.'),
        ).toBeVisible();
        await alphaPage.locator('#seller-product-status').click();
        await alphaPage.getByRole('option', { name: 'Pending Review' }).click();
        await alphaPage
            .getByRole('button', { name: 'Save Product Changes' })
            .click();
        await expect(
            alphaPage.getByText('Pending Moderation Review'),
        ).toBeVisible();

        // Admin approves live
        await adminPage.reload({ waitUntil: 'networkidle' });
        await adminPage
            .getByRole('button', { name: 'Approve & Publish Live' })
            .click();
        await expect(adminPage.getByText('Active').first()).toBeVisible();

        // Live product now appears on public storefront catalog!
        await adminPage.goto(`${mv3BaseUrl}/catalog`, {
            waitUntil: 'networkidle',
        });
        await expect(
            adminPage.getByText('MV3 Studio Ribbon Microphone').first(),
        ).toBeVisible();
        await adminCtx.close();

        // 4. Seller A updates stock atomically on /seller/inventory without unpublishing product
        await alphaPage.goto(`${mv3BaseUrl}/seller/inventory`, {
            waitUntil: 'networkidle',
        });
        await expect(
            alphaPage.getByText('MV3-RIBBON-001').first(),
        ).toBeVisible();
        await alphaPage
            .getByRole('button', { name: 'Adjust Stock' })
            .first()
            .click();
        await alphaPage.getByLabel('New Stock Count').fill('27');
        await alphaPage
            .getByRole('button', { name: 'Save Adjustment' })
            .click();
        await expect(alphaPage.getByText('27 in stock')).toBeVisible();

        await alphaPage.setViewportSize({ width: 375, height: 812 });
        const inventoryMobileOverflow = await alphaPage.evaluate(
            () =>
                document.documentElement.scrollWidth >
                document.documentElement.clientWidth,
        );
        expect(inventoryMobileOverflow).toBe(false);
        await alphaPage.setViewportSize({ width: 1280, height: 800 });

        if (!process.env.CI && fs.existsSync(artifactsDir)) {
            await alphaPage.screenshot({
                path: path.join(artifactsDir, 'mv3_seller_inventory.png'),
            });
        }

        await alphaCtx.close();
        expect(errors).toEqual([]);
    });
});
