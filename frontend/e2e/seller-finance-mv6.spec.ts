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
    'storage/framework/playwright-mv6-e2e.sqlite',
);
const mv6Port = process.env.PLAYWRIGHT_MV6_PORT ?? '18090';
const mv6BaseUrl = `http://127.0.0.1:${mv6Port}`;

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
let sharedEnv: NodeJS.ProcessEnv = process.env;

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
    .serial('Phase MV-6 Commissions, Seller Ledger & Super Admin Payouts', () => {
    test.beforeAll(async () => {
        if (fs.existsSync(isolatedDbPath)) {
            fs.unlinkSync(isolatedDbPath);
        }
        fs.writeFileSync(isolatedDbPath, '');

        sharedEnv = {
            ...process.env,
            APP_ENV: 'local',
            DB_CONNECTION: 'sqlite',
            DB_DATABASE: isolatedDbPath,
            APP_URL: mv6BaseUrl,
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
                env: sharedEnv,
                stdio: 'pipe',
            },
        );

        const seedPhp = `
$buyer = App\\Models\\User::factory()->create([
    'name' => 'MV6 Buyer',
    'email' => 'buyer.mv6@gmail.com',
    'password' => 'Mv6!Pass2026',
]);
App\\Domain\\Customer\\Models\\CustomerAddress::factory()->defaultShipping()->create([
    'user_id' => $buyer->id,
    'label' => 'Home Studio',
    'recipient_name' => 'MV6 Buyer',
    'phone' => '+63 917 555 0166',
    'address_line_1' => '88 Salcedo Street',
    'barangay' => 'San Lorenzo',
    'city' => 'Makati City',
    'province' => 'Metro Manila',
    'postal_code' => '1229',
]);
App\\Models\\User::factory()->admin()->create([
    'name' => 'Super Admin MV6',
    'email' => 'admin.mv6@gmail.com',
    'password' => 'Mv6!Pass2026',
]);
App\\Models\\User::factory()->staff([App\\Domain\\Identity\\Enums\\StaffPermission::OrdersManage->value])->create([
    'name' => 'Staff Operations MV6',
    'email' => 'staff.mv6@gmail.com',
    'password' => 'Mv6!Pass2026',
]);
$category = App\\Domain\\Catalog\\Models\\Category::factory()->create([
    'name' => 'Specialty Roasts',
    'slug' => 'specialty-roasts',
    'is_active' => true,
]);
$seller = App\\Models\\User::factory()->seller()->create([
    'name' => 'Roaster Owner',
    'email' => 'seller.mv6@gmail.com',
    'password' => 'Mv6!Pass2026',
]);
$store = App\\Domain\\Seller\\Models\\Store::query()->create([
    'owner_user_id' => $seller->id,
    'name' => 'Manila Craft Roasters',
    'slug' => 'manila-craft-roasters',
    'status' => 'active',
    'is_official' => false,
]);
App\\Domain\\Seller\\Models\\StoreMembership::query()->create([
    'store_id' => $store->id,
    'user_id' => $seller->id,
    'role' => 'owner',
]);
App\\Domain\\Seller\\Models\\SellerProfile::query()->create([
    'user_id' => $seller->id,
    'store_id' => $store->id,
    'business_name' => 'Manila Craft Roasters Corp',
    'business_type' => 'sole_proprietor',
    'contact_phone' => '+63 917 555 0161',
    'business_address' => 'Makati City',
    'status' => 'approved',
    'submitted_at' => now(),
    'reviewed_at' => now(),
    'approved_at' => now(),
]);
App\\Domain\\Catalog\\Models\\Product::factory()->create([
    'category_id' => $category->id,
    'store_id' => $store->id,
    'seller_name' => $store->name,
    'name' => 'Cordillera Reserve Beans',
    'slug' => 'cordillera-reserve-beans',
    'sku' => 'MV6-CRB-01',
    'base_price' => '2000.00',
    'stock_quantity' => 15,
    'status' => App\\Domain\\Catalog\\Enums\\ProductStatus::Active,
]);
`;

        execFileSync(
            phpCmd,
            [...phpExtensions, 'artisan', 'tinker', '--execute', seedPhp],
            {
                cwd: backendRoot,
                env: sharedEnv,
                stdio: 'pipe',
            },
        );

        serverProcess = spawn(
            phpCmd,
            [
                ...phpExtensions,
                '-S',
                `127.0.0.1:${mv6Port}`,
                '../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php',
            ],
            {
                cwd: path.join(backendRoot, 'public'),
                env: sharedEnv,
                stdio: 'ignore',
            },
        );

        await waitForServer(mv6BaseUrl);
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

    test('enforces commission snapshot, 7-day post-delivery hold, seller payout withdrawal, and super admin disbursement confirmation', async ({
        browser,
    }) => {
        test.setTimeout(150_000);
        const pageErrors: string[] = [];

        // 1. Buyer purchases PHP 2,000.00 item and completes sandbox payment.
        const buyerCtx = await browser.newContext({
            viewport: { width: 1280, height: 800 },
        });
        const buyerPage = await buyerCtx.newPage();
        buyerPage.on('pageerror', (err) => pageErrors.push(err.message));

        await buyerPage.goto(`${mv6BaseUrl}/login`, {
            waitUntil: 'networkidle',
        });
        await buyerPage.getByLabel('Email address').fill('buyer.mv6@gmail.com');
        await buyerPage
            .getByRole('textbox', { name: 'Password' })
            .fill('Mv6!Pass2026');
        await buyerPage.getByRole('button', { name: 'Log in' }).click();
        await expect(buyerPage).not.toHaveURL(/\/login/);

        await buyerPage.goto(`${mv6BaseUrl}/stores/manila-craft-roasters`, {
            waitUntil: 'networkidle',
        });
        await buyerPage
            .getByRole('button', {
                name: 'Add Cordillera Reserve Beans to cart',
            })
            .click();
        await buyerPage.waitForLoadState('networkidle');

        await buyerPage.goto(`${mv6BaseUrl}/checkout`, {
            waitUntil: 'networkidle',
        });
        await expect(buyerPage.getByText('₱2,000.00').first()).toBeVisible();
        await buyerPage
            .getByRole('button', { name: 'Place Order & Pay with PayMongo' })
            .click();

        await expect(buyerPage).toHaveURL(/\/checkout\/sandbox\/\d+/);
        await buyerPage.getByRole('button', { name: 'Simulate Paid' }).click();
        await expect(buyerPage).toHaveURL(/\/checkout\/success\/\d+/);
        await buyerCtx.close();

        // 2. Seller delivers the suborder and inspects the 7-day hold & 5% commission snapshot.
        const sellerCtx = await browser.newContext({
            viewport: { width: 1280, height: 800 },
        });
        const sellerPage = await sellerCtx.newPage();
        sellerPage.on('pageerror', (err) => pageErrors.push(err.message));

        await sellerPage.goto(`${mv6BaseUrl}/login`, {
            waitUntil: 'networkidle',
        });
        await sellerPage
            .getByLabel('Email address')
            .fill('seller.mv6@gmail.com');
        await sellerPage
            .getByRole('textbox', { name: 'Password' })
            .fill('Mv6!Pass2026');
        await sellerPage.getByRole('button', { name: 'Log in' }).click();
        await expect(sellerPage).toHaveURL(/\/seller\/dashboard/);

        await expect(sellerPage.getByText('Available Payout')).toBeVisible();

        await sellerPage
            .getByRole('link', { name: 'Orders & Fulfillment' })
            .click();
        await expect(sellerPage).toHaveURL(/\/seller\/orders/);
        await sellerPage.getByRole('link', { name: 'Manage' }).first().click();
        await expect(sellerPage).toHaveURL(/\/seller\/orders\/\d+/);

        // Paid -> Processing
        await sellerPage
            .getByRole('button', { name: 'Update Fulfillment Status' })
            .click();
        await expect(
            sellerPage.getByText('Processing', { exact: true }).first(),
        ).toBeVisible();

        // Processing -> Shipped
        await sellerPage.getByLabel('Shipping Carrier').fill('LBC Express');
        await sellerPage.getByLabel('Tracking Number').fill('LBC-MV6-101');
        await sellerPage
            .getByRole('button', { name: 'Update Fulfillment Status' })
            .click();
        await expect(sellerPage.getByText('LBC-MV6-101').first()).toBeVisible();

        // Shipped -> Delivered
        await sellerPage
            .getByRole('button', { name: 'Update Fulfillment Status' })
            .click();
        await expect(
            sellerPage.getByText('Delivered', { exact: true }).first(),
        ).toBeVisible();

        await sellerPage
            .getByRole('link', { name: 'Finance & Payouts' })
            .first()
            .click();
        await expect(sellerPage).toHaveURL(/\/seller\/finance/);
        await expect(sellerPage.getByText('₱1,900.00').first()).toBeVisible();
        await expect(sellerPage.getByText('₱100.00').first()).toBeVisible();
        await expect(sellerPage.getByText('5.00%').first()).toBeVisible();
        await expect(
            sellerPage.getByRole('button', { name: 'Submit Payout Request' }),
        ).toBeDisabled();

        await sellerPage.screenshot({
            path: path.join(artifactsDir, 'mv6_seller_finance_hold.png'),
            fullPage: true,
        });

        // 3. Mature the 7-day hold in isolated SQLite so the seller can request withdrawal cleanly.
        const matureHoldPhp = `
App\\Domain\\Orders\\Models\\SellerOrder::query()->update([
    'delivered_at' => now()->subDays(8),
    'hold_until' => now()->subDay(),
]);
`;
        execFileSync(
            phpCmd,
            [...phpExtensions, 'artisan', 'tinker', '--execute', matureHoldPhp],
            {
                cwd: backendRoot,
                env: sharedEnv,
                stdio: 'pipe',
            },
        );

        await sellerPage.reload({ waitUntil: 'networkidle' });
        await expect(
            sellerPage.getByRole('button', { name: 'Submit Payout Request' }),
        ).toBeEnabled();

        await sellerPage.getByLabel('Amount (PHP)').fill('1200');
        await sellerPage
            .getByLabel('Account / Wallet Details')
            .fill('BPI Account **** 7788');
        await sellerPage
            .getByLabel('Notes (Optional)')
            .fill('Weekly MV6 settlement');
        await sellerPage
            .getByRole('button', { name: 'Submit Payout Request' })
            .click();

        await expect(
            sellerPage.getByText('Pending Review').first(),
        ).toBeVisible();
        await expect(sellerPage.getByText('₱1,200.00').first()).toBeVisible();
        await expect(sellerPage.getByText('₱700.00').first()).toBeVisible();

        // Mobile viewport overflow verification on /seller/finance (checks both document overflow and card bounds).
        await sellerPage.setViewportSize({ width: 375, height: 812 });
        const mobileOverflowStats = await sellerPage.evaluate(() => {
            const headings = Array.from(
                document.querySelectorAll('h1, h2, h3, button'),
            );
            const maxRight = headings.reduce(
                (max, el) => Math.max(max, el.getBoundingClientRect().right),
                0,
            );
            return {
                docOverflow:
                    document.documentElement.scrollWidth -
                    document.documentElement.clientWidth,
                maxRight,
            };
        });
        expect(mobileOverflowStats.docOverflow).toBeLessThanOrEqual(1);
        expect(mobileOverflowStats.maxRight).toBeLessThanOrEqual(375);

        await sellerPage.screenshot({
            path: path.join(artifactsDir, 'mv6_mobile_seller_finance.png'),
            fullPage: true,
        });

        // 4. Verify Staff without Super Admin receives 403 on /admin/finance.
        const staffCtx = await browser.newContext({
            viewport: { width: 1280, height: 800 },
        });
        const staffPage = await staffCtx.newPage();
        await staffPage.goto(`${mv6BaseUrl}/login`, {
            waitUntil: 'networkidle',
        });
        await staffPage.getByLabel('Email address').fill('staff.mv6@gmail.com');
        await staffPage
            .getByRole('textbox', { name: 'Password' })
            .fill('Mv6!Pass2026');
        await staffPage.getByRole('button', { name: 'Log in' }).click();
        await expect(staffPage).not.toHaveURL(/\/login/);

        const staffFinanceRes = await staffPage.request.get(
            `${mv6BaseUrl}/admin/finance`,
        );
        expect(staffFinanceRes.status()).toBe(403);
        await staffCtx.close();

        // 5. Super Admin signs in -> verifies /admin/finance KPIs, approves & marks Disbursed with reference.
        const adminCtx = await browser.newContext({
            viewport: { width: 1280, height: 800 },
        });
        const adminPage = await adminCtx.newPage();
        adminPage.on('pageerror', (err) => pageErrors.push(err.message));

        await adminPage.goto(`${mv6BaseUrl}/login`, {
            waitUntil: 'networkidle',
        });
        await adminPage.getByLabel('Email address').fill('admin.mv6@gmail.com');
        await adminPage
            .getByRole('textbox', { name: 'Password' })
            .fill('Mv6!Pass2026');
        await adminPage.getByRole('button', { name: 'Log in' }).click();
        await expect(adminPage).not.toHaveURL(/\/login/);

        await adminPage.goto(`${mv6BaseUrl}/admin/finance`, {
            waitUntil: 'networkidle',
        });
        await expect(
            adminPage.getByText('Manila Craft Roasters').first(),
        ).toBeVisible();
        await expect(adminPage.getByText('₱100.00').first()).toBeVisible();
        await expect(adminPage.getByText('₱1,200.00').first()).toBeVisible();

        // Step A: Pending Review -> Approved (defaults to Approved); wait until action selector switches to Paid / Disbursed
        await adminPage.getByLabel('Review Note').fill('KYC & ledger verified');
        await adminPage.getByRole('button', { name: 'Apply Payout' }).click();
        await expect(
            adminPage.getByRole('combobox', { name: 'Select payout action' }),
        ).toContainText('Paid / Disbursed');
        await expect(
            adminPage.getByText('Approved (Awaiting Transfer)').first(),
        ).toBeVisible();

        // Step B: Approved -> Paid / Disbursed with Bank Transfer Ref
        await adminPage.getByLabel('Transfer Ref').fill('UBP-MV6-9001');
        await adminPage
            .getByLabel('Review Note')
            .fill('Bank disbursement completed');
        await adminPage.getByRole('button', { name: 'Apply Payout' }).click();
        await expect(
            adminPage.getByText('0 open withdrawal request(s).'),
        ).toBeVisible();
        await expect(
            adminPage.getByText('Paid / Disbursed').first(),
        ).toBeVisible();
        await expect(adminPage.getByText('UBP-MV6-9001').first()).toBeVisible();

        await adminPage.screenshot({
            path: path.join(artifactsDir, 'mv6_admin_finance.png'),
            fullPage: true,
        });
        await adminCtx.close();

        // 6. Seller reloads /seller/finance desktop view and confirms Paid receipt & ledger debit.
        await sellerPage.setViewportSize({ width: 1280, height: 800 });
        await sellerPage.reload({ waitUntil: 'networkidle' });
        await expect(
            sellerPage.getByText('Paid / Disbursed').first(),
        ).toBeVisible();
        await expect(
            sellerPage.getByText('UBP-MV6-9001').first(),
        ).toBeVisible();
        await expect(sellerPage.getByText('-₱1,200.00').first()).toBeVisible();

        await sellerPage.screenshot({
            path: path.join(artifactsDir, 'mv6_seller_finance_paid.png'),
            fullPage: true,
        });

        await sellerCtx.close();

        expect(pageErrors).toEqual([]);
    });
});
