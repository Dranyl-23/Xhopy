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
    'storage/framework/playwright-batch5-e2e.sqlite',
);
const batch5Port = process.env.PLAYWRIGHT_BATCH5_PORT ?? '18084';
const batch5BaseUrl = `http://127.0.0.1:${batch5Port}`;

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
    .serial('Batch 5 Cart, Checkout, PayMongo Sandbox & Orders', () => {
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
            APP_URL: batch5BaseUrl,
            MAIL_MAILER: 'log',
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

        execFileSync(
            phpCmd,
            [
                ...phpExtensions,
                'artisan',
                'tinker',
                '--execute',
                [
                    "$user = App\\Models\\User::factory()->create(['name' => 'Batch5 Buyer', 'email' => 'buyer.batch5@gmail.com', 'password' => 'Batch5!Pass2026']);",
                    "App\\Domain\\Customer\\Models\\CustomerAddress::factory()->defaultShipping()->create(['user_id' => $user->id, 'label' => 'Home Office', 'recipient_name' => 'Batch5 Buyer', 'phone' => '+63 917 555 0199', 'address_line_1' => '42 Ayala Avenue', 'barangay' => 'San Lorenzo', 'city' => 'Makati City', 'province' => 'Metro Manila', 'postal_code' => '1223']);",
                    "$category = App\\Domain\\Catalog\\Models\\Category::factory()->create(['name' => 'Audio Gear', 'slug' => 'audio-gear', 'is_active' => true]);",
                    "$brand = App\\Domain\\Catalog\\Models\\Brand::factory()->create(['name' => 'XHopy Studio', 'slug' => 'xhopy-studio', 'is_active' => true]);",
                    "App\\Domain\\Catalog\\Models\\Product::factory()->create(['category_id' => $category->id, 'brand_id' => $brand->id, 'name' => 'XHopy Reference Studio Headphones', 'slug' => 'batch5-studio-headphones', 'sku' => 'B5-AUD-001', 'base_price' => '6490.00', 'stock_quantity' => 5, 'status' => App\\Domain\\Catalog\\Enums\\ProductStatus::Active]);",
                ].join(' '),
            ],
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
                `127.0.0.1:${batch5Port}`,
                '../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php',
            ],
            {
                cwd: path.join(backendRoot, 'public'),
                env: serverEnv,
                stdio: 'ignore',
            },
        );

        await waitForServer(batch5BaseUrl);
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

    test('customer adds product to cart, adjusts quantity, checks out via PayMongo sandbox, and inspects order details on desktop and mobile', async ({
        page,
    }) => {
        const errors: string[] = [];
        page.on('pageerror', (err) => errors.push(err.message));

        // 1. Desktop sign in
        await page.setViewportSize({ width: 1280, height: 800 });
        await page.goto(`${batch5BaseUrl}/login`, { waitUntil: 'networkidle' });
        await page.getByLabel('Email address').fill('buyer.batch5@gmail.com');
        await page
            .getByRole('textbox', { name: 'Password' })
            .fill('Batch5!Pass2026');
        await page.getByRole('button', { name: 'Log in' }).click();
        await expect(page).toHaveURL(/\/dashboard/);

        // 2. Navigate to product detail and add to cart
        await page.goto(`${batch5BaseUrl}/products/batch5-studio-headphones`, {
            waitUntil: 'networkidle',
        });

        await page.getByRole('button', { name: 'Add to Cart' }).click();
        await expect(
            page.getByRole('button', { name: /Shopping cart/i }),
        ).toContainText('1');

        await page.getByRole('link', { name: 'View Cart' }).click();
        await expect(page).toHaveURL(/\/cart/);
        await expect(
            page.getByRole('heading', { level: 1, name: 'Shopping Cart' }),
        ).toBeVisible();
        await expect(page.getByText('₱6,490.00').first()).toBeVisible();

        // Increment quantity from 1 -> 2 using QuantitySelector plus button
        const firstArticle = page.locator('article').first();
        await firstArticle.getByRole('button').nth(1).click();
        await expect(page.getByText('₱12,980.00').first()).toBeVisible();

        if (!process.env.CI && fs.existsSync(artifactsDir)) {
            await page.screenshot({
                path: path.join(artifactsDir, 'batch5_cart.png'),
            });
        }

        // 3. Proceed to Checkout
        await page.getByRole('link', { name: 'Proceed to Checkout' }).click();
        await expect(page).toHaveURL(/\/checkout$/);
        await expect(
            page.getByRole('heading', { level: 1, name: 'Secure Checkout' }),
        ).toBeVisible();
        await expect(page.getByText('Home Office')).toBeVisible();
        await expect(page.getByText('₱12,980.00').first()).toBeVisible();

        await page
            .getByLabel('Order Notes')
            .fill('Deliver during business hours please.');

        if (!process.env.CI && fs.existsSync(artifactsDir)) {
            await page.screenshot({
                path: path.join(artifactsDir, 'batch5_checkout.png'),
            });
        }

        await page
            .getByRole('button', { name: 'Place Order & Pay with PayMongo' })
            .click();

        // 4. Local PayMongo Sandbox Simulator -> Paid
        await expect(page).toHaveURL(/\/checkout\/sandbox\/\d+/);
        await expect(
            page.getByText('Local PayMongo Checkout Sandbox'),
        ).toBeVisible();
        await expect(page.getByText('₱12,980.00').first()).toBeVisible();

        await page.getByRole('button', { name: 'Simulate Paid' }).click();

        // 5. Verified Success Result -> View Order Detail
        await expect(page).toHaveURL(/\/checkout\/success\/\d+/);
        await expect(page.getByText('Payment Confirmed')).toBeVisible();
        await expect(page.getByText('₱12,980.00').first()).toBeVisible();

        await page.getByRole('link', { name: 'View Order' }).click();
        await expect(page).toHaveURL(/\/orders\/\d+/);
        await expect(page.getByText('Order Items Snapshot')).toBeVisible();
        await expect(
            page.getByText('Home Office').or(page.getByText('42 Ayala Avenue')),
        ).toBeVisible();
        await expect(page.getByText('Paid').first()).toBeVisible();

        if (!process.env.CI && fs.existsSync(artifactsDir)) {
            await page.screenshot({
                path: path.join(artifactsDir, 'batch5_order_detail.png'),
            });
        }

        // 6. Mobile responsiveness & horizontal overflow check across /orders & /cart
        await page.setViewportSize({ width: 375, height: 812 });
        await page.goto(`${batch5BaseUrl}/orders`, {
            waitUntil: 'networkidle',
        });
        await expect(
            page.getByRole('heading', { level: 1, name: 'My Orders' }),
        ).toBeVisible();

        const ordersOverflow = await page.evaluate(
            () =>
                document.documentElement.scrollWidth >
                document.documentElement.clientWidth,
        );
        expect(ordersOverflow).toBe(false);

        await page.goto(`${batch5BaseUrl}/cart`, { waitUntil: 'networkidle' });
        const cartOverflow = await page.evaluate(
            () =>
                document.documentElement.scrollWidth >
                document.documentElement.clientWidth,
        );
        expect(cartOverflow).toBe(false);
        expect(errors).toEqual([]);
    });
});
