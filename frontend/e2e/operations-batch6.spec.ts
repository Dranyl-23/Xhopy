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
    'storage/framework/playwright-batch6-e2e.sqlite',
);
const batch6Port = process.env.PLAYWRIGHT_BATCH6_PORT ?? '18085';
const batch6BaseUrl = `http://127.0.0.1:${batch6Port}`;

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
    .serial('Batch 6 Operations, Inventory, Analytics & Notifications', () => {
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
            APP_URL: batch6BaseUrl,
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

        execFileSync(
            phpCmd,
            [
                ...phpExtensions,
                'artisan',
                'tinker',
                '--execute',
                [
                    "App\\Models\\User::factory()->admin()->create(['name' => 'Operations Admin', 'email' => 'admin.batch6@gmail.com', 'password' => 'Batch6!Pass2026']);",
                    "$buyer = App\\Models\\User::factory()->create(['name' => 'Batch6 Customer', 'email' => 'buyer.batch6@gmail.com', 'password' => 'Batch6!Pass2026']);",
                    "$addr = App\\Domain\\Customer\\Models\\CustomerAddress::factory()->defaultShipping()->create(['user_id' => $buyer->id, 'recipient_name' => 'Batch6 Customer', 'address_line_1' => '88 Bonifacio High St']);",
                    "$cat = App\\Domain\\Catalog\\Models\\Category::factory()->create(['name' => 'Desk Hardware', 'slug' => 'desk-hardware', 'is_active' => true]);",
                    "$product = App\\Domain\\Catalog\\Models\\Product::factory()->create(['category_id' => $cat->id, 'name' => 'XHopy Desk Mat Pro', 'slug' => 'xhopy-desk-mat-pro', 'sku' => 'B6-DESK-001', 'base_price' => '2450.00', 'stock_quantity' => 4, 'status' => App\\Domain\\Catalog\\Enums\\ProductStatus::Active]);",
                    "$order = App\\Domain\\Orders\\Models\\Order::query()->create(['order_number' => 'XHP-B6-1001', 'user_id' => $buyer->id, 'customer_address_id' => $addr->id, 'idempotency_key' => 'b6-idem-1001', 'status' => 'paid', 'payment_status' => 'paid', 'currency' => 'PHP', 'subtotal_amount' => '4900.00', 'shipping_amount' => '0.00', 'discount_amount' => '0.00', 'tax_amount' => '0.00', 'total_amount' => '4900.00', 'shipping_recipient_name' => $addr->recipient_name, 'shipping_phone' => $addr->phone, 'shipping_country_code' => 'PH', 'shipping_province' => $addr->province, 'shipping_city' => $addr->city, 'shipping_barangay' => $addr->barangay, 'shipping_postal_code' => $addr->postal_code, 'shipping_address_line_1' => $addr->address_line_1, 'placed_at' => now(), 'paid_at' => now()]);",
                    "App\\Domain\\Orders\\Models\\OrderItem::query()->create(['order_id' => $order->id, 'product_id' => $product->id, 'seller_name' => 'XHopy Official', 'product_name' => $product->name, 'sku' => $product->sku, 'currency' => 'PHP', 'unit_price' => '2450.00', 'quantity' => 2, 'line_total' => '4900.00']);",
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
                `127.0.0.1:${batch6Port}`,
                '../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php',
            ],
            {
                cwd: path.join(backendRoot, 'public'),
                env: serverEnv,
                stdio: 'ignore',
            },
        );

        await waitForServer(batch6BaseUrl);
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

    test('admin views analytics, fulfills order with tracking, reconciles inventory, and customer sees order notification', async ({
        browser,
    }) => {
        const errors: string[] = [];

        // 1. Admin workflows on Desktop
        const adminContext = await browser.newContext({
            viewport: { width: 1280, height: 800 },
        });
        const adminPage = await adminContext.newPage();
        adminPage.on('pageerror', (err) => errors.push(err.message));

        await adminPage.goto(`${batch6BaseUrl}/login`, {
            waitUntil: 'networkidle',
        });
        await adminPage
            .getByLabel('Email address')
            .fill('admin.batch6@gmail.com');
        await adminPage
            .getByRole('textbox', { name: 'Password' })
            .fill('Batch6!Pass2026');
        await adminPage.getByRole('button', { name: 'Log in' }).click();
        await expect(adminPage).toHaveURL(/\/dashboard/);

        // Operations & Analytics
        await adminPage.goto(`${batch6BaseUrl}/admin/operations`, {
            waitUntil: 'networkidle',
        });
        await expect(
            adminPage.getByRole('heading', {
                level: 1,
                name: /Operations & Store Analytics/i,
            }),
        ).toBeVisible();
        await expect(adminPage.getByText('₱4,900.00').first()).toBeVisible();
        await expect(
            adminPage.getByText('XHopy Desk Mat Pro').first(),
        ).toBeVisible();

        if (!process.env.CI && fs.existsSync(artifactsDir)) {
            await adminPage.screenshot({
                path: path.join(artifactsDir, 'batch6_operations.png'),
            });
        }

        // Orders & Fulfillment transition
        await adminPage.goto(`${batch6BaseUrl}/admin/orders`, {
            waitUntil: 'networkidle',
        });
        await expect(adminPage.getByText('XHP-B6-1001')).toBeVisible();
        await adminPage.getByRole('link', { name: 'Manage' }).first().click();

        await expect(adminPage).toHaveURL(/\/admin\/orders\/\d+/);
        // Advance Paid -> Processing
        await adminPage
            .getByRole('button', { name: 'Update Order Status' })
            .click();
        await expect(adminPage.getByText('Processing').first()).toBeVisible();

        // Advance Processing -> Shipped (with Carrier & Tracking)
        await adminPage.locator('#admin-order-next-status').click();
        await adminPage.getByRole('option', { name: 'Shipped' }).click();
        await adminPage.getByLabel('Shipping Carrier').fill('J&T Express');
        await adminPage.getByLabel('Tracking Number').fill('JT-B6-90012');
        await adminPage
            .getByRole('button', { name: 'Update Order Status' })
            .click();

        await expect(adminPage.getByText('Shipped').first()).toBeVisible();
        await expect(adminPage.getByText('JT-B6-90012')).toBeVisible();

        if (!process.env.CI && fs.existsSync(artifactsDir)) {
            await adminPage.screenshot({
                path: path.join(artifactsDir, 'batch6_order_manage.png'),
            });
        }

        // Inventory Reconciliation
        await adminPage.goto(`${batch6BaseUrl}/admin/inventory`, {
            waitUntil: 'networkidle',
        });
        await expect(
            adminPage.getByRole('heading', {
                level: 1,
                name: /Inventory Reconciliation/i,
            }),
        ).toBeVisible();
        await adminPage
            .getByRole('button', { name: 'Adjust Stock' })
            .first()
            .click();
        await adminPage.getByLabel('New Stock Count').fill('20');
        await adminPage
            .getByLabel('Reason / Audit Reference')
            .fill('Batch 6 cycle count');
        await adminPage
            .getByRole('button', { name: 'Save Adjustment' })
            .click();

        await expect(adminPage.getByText('20 in stock')).toBeVisible();
        await expect(adminPage.getByText('+16')).toBeVisible();

        if (!process.env.CI && fs.existsSync(artifactsDir)) {
            await adminPage.screenshot({
                path: path.join(artifactsDir, 'batch6_inventory.png'),
            });
        }

        // Mobile viewport overflow check for admin operations
        await adminPage.setViewportSize({ width: 375, height: 812 });
        await adminPage.goto(`${batch6BaseUrl}/admin/operations`, {
            waitUntil: 'networkidle',
        });
        const adminMobileOverflow = await adminPage.evaluate(
            () =>
                document.documentElement.scrollWidth >
                document.documentElement.clientWidth,
        );
        expect(adminMobileOverflow).toBe(false);
        await adminContext.close();

        // 2. Customer receives DB notification & shipment tracking
        const buyerContext = await browser.newContext({
            viewport: { width: 1280, height: 800 },
        });
        const buyerPage = await buyerContext.newPage();
        buyerPage.on('pageerror', (err) => errors.push(err.message));

        await buyerPage.goto(`${batch6BaseUrl}/login`, {
            waitUntil: 'networkidle',
        });
        await buyerPage
            .getByLabel('Email address')
            .fill('buyer.batch6@gmail.com');
        await buyerPage
            .getByRole('textbox', { name: 'Password' })
            .fill('Batch6!Pass2026');
        await buyerPage.getByRole('button', { name: 'Log in' }).click();
        await expect(buyerPage).toHaveURL(/\/dashboard/);

        await buyerPage.goto(`${batch6BaseUrl}/orders`, {
            waitUntil: 'networkidle',
        });
        await expect(
            buyerPage.getByText('Order XHP-B6-1001: Shipped'),
        ).toBeVisible();
        await buyerPage.getByRole('button', { name: 'Mark All Read' }).click();

        await buyerPage
            .getByRole('link', { name: 'View Order Details' })
            .first()
            .click();
        await expect(buyerPage).toHaveURL(/\/orders\/\d+/);
        await expect(buyerPage.getByText('J&T Express')).toBeVisible();
        await expect(buyerPage.getByText('JT-B6-90012')).toBeVisible();

        await buyerContext.close();
        expect(errors).toEqual([]);
    });
});
