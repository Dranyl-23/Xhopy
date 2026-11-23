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
    'storage/framework/playwright-mv2-e2e.sqlite',
);
const mv2Port = process.env.PLAYWRIGHT_MV2_PORT ?? '18086';
const mv2BaseUrl = `http://127.0.0.1:${mv2Port}`;

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

// Minimal valid 1x1 PNG buffer (guaranteed to pass finfo MIME verification).
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
            // Retry until server binds
        }
        await new Promise((r) => setTimeout(r, 250));
    }
    throw new Error(`Timed out waiting for isolated server at ${url}`);
}

test.describe
    .serial('Phase MV-1 + MV-2 Seller Onboarding & Super Admin Approval', () => {
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
            APP_URL: mv2BaseUrl,
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
                    "App\\Models\\User::factory()->admin()->create(['name' => 'Super Admin MV2', 'email' => 'superadmin.mv2@gmail.com', 'password' => 'Mv2!Pass2026']);",
                    "App\\Models\\User::factory()->staff(['sellers.review'])->create(['name' => 'Seller Reviewer MV2', 'email' => 'reviewer.mv2@gmail.com', 'password' => 'Mv2!Pass2026']);",
                    "App\\Models\\User::factory()->create(['name' => 'Applicant Buyer', 'email' => 'seller.mv2@gmail.com', 'password' => 'Mv2!Pass2026']);",
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
                `127.0.0.1:${mv2Port}`,
                '../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php',
            ],
            {
                cwd: path.join(backendRoot, 'public'),
                env: serverEnv,
                stdio: 'ignore',
            },
        );

        await waitForServer(mv2BaseUrl);
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

    test('applicant applies, uploads private doc, staff triages, super admin approves, and seller accesses dashboard', async ({
        browser,
    }) => {
        test.setTimeout(60_000);
        const errors: string[] = [];

        // 1. Customer submits seller onboarding & verification document on mobile + desktop
        const sellerContext = await browser.newContext({
            viewport: { width: 1280, height: 800 },
        });
        const sellerPage = await sellerContext.newPage();
        sellerPage.on('pageerror', (err) => errors.push(err.message));

        await sellerPage.goto(`${mv2BaseUrl}/login`, {
            waitUntil: 'networkidle',
        });
        await sellerPage
            .getByLabel('Email address')
            .fill('seller.mv2@gmail.com');
        await sellerPage
            .getByRole('textbox', { name: 'Password' })
            .fill('Mv2!Pass2026');
        await sellerPage.getByRole('button', { name: 'Log in' }).click();
        await expect(sellerPage).toHaveURL(/\/dashboard/);
        await expect(
            sellerPage.getByText('Customer Portal').first(),
        ).toBeVisible();
        await expect(
            sellerPage.getByRole('link', { name: 'Wishlist', exact: true }),
        ).toBeVisible();

        // Verify unauthorized access renders branded 403 XHopy page
        await sellerPage.goto(`${mv2BaseUrl}/admin/users`, {
            waitUntil: 'networkidle',
        });
        await expect(sellerPage.getByText('403 Unauthorized')).toBeVisible();

        // Check /wishlist renders empty state cleanly inside Customer Portal
        await sellerPage.goto(`${mv2BaseUrl}/wishlist`, {
            waitUntil: 'networkidle',
        });
        await expect(
            sellerPage.getByText('Your wishlist is empty'),
        ).toBeVisible();

        await sellerPage.goto(`${mv2BaseUrl}/dashboard`, {
            waitUntil: 'networkidle',
        });
        await expect(
            sellerPage
                .getByRole('main')
                .getByRole('link', { name: 'Become a Seller' }),
        ).toBeVisible();

        await sellerPage
            .getByRole('main')
            .getByRole('link', { name: 'Become a Seller' })
            .click();
        await expect(sellerPage).toHaveURL(/\/seller\/onboarding/);

        // Check mobile 375px zero horizontal overflow on onboarding form
        await sellerPage.setViewportSize({ width: 375, height: 812 });
        const mobileOverflow = await sellerPage.evaluate(
            () =>
                document.documentElement.scrollWidth >
                document.documentElement.clientWidth,
        );
        expect(mobileOverflow).toBe(false);
        await sellerPage.setViewportSize({ width: 1280, height: 800 });

        await sellerPage.getByLabel('Store Name').fill('Quezon Craft Audio');
        await sellerPage
            .getByLabel('Registered Business / Full Name')
            .fill('Quezon Craft Audio OPC');
        await sellerPage.getByLabel('Contact Phone').fill('+63 917 880 1122');
        await sellerPage
            .getByLabel('Business / Warehouse Address')
            .fill('45 Katipunan Ave, Quezon City 1108');
        await sellerPage
            .getByRole('button', { name: 'Submit Seller Application' })
            .click();

        await expect(
            sellerPage.getByText('Pending Review').first(),
        ).toBeVisible();

        await sellerPage.locator('#verification_document').setInputFiles({
            name: 'government-id.png',
            mimeType: 'image/png',
            buffer: Buffer.from(minimalPngBase64, 'base64'),
        });
        await sellerPage
            .getByRole('button', { name: 'Upload Verification Document' })
            .click();

        await expect(sellerPage.getByText('government-id.png')).toBeVisible();

        // Visiting /seller/dashboard while Pending redirects cleanly back to onboarding
        await sellerPage.goto(`${mv2BaseUrl}/seller/dashboard`, {
            waitUntil: 'networkidle',
        });
        await expect(sellerPage).toHaveURL(/\/seller\/onboarding/);

        if (!process.env.CI && fs.existsSync(artifactsDir)) {
            await sellerPage.screenshot({
                path: path.join(artifactsDir, 'mv2_seller_onboarding.png'),
            });
        }

        // 2. Granular Staff Reviewer (sellers.review only) lands on /admin/sellers and triages Pending -> Under Review
        const reviewerContext = await browser.newContext({
            viewport: { width: 1280, height: 800 },
        });
        const reviewerPage = await reviewerContext.newPage();
        reviewerPage.on('pageerror', (err) => errors.push(err.message));

        await reviewerPage.goto(`${mv2BaseUrl}/login`, {
            waitUntil: 'networkidle',
        });
        await reviewerPage
            .getByLabel('Email address')
            .fill('reviewer.mv2@gmail.com');
        await reviewerPage
            .getByRole('textbox', { name: 'Password' })
            .fill('Mv2!Pass2026');
        await reviewerPage.getByRole('button', { name: 'Log in' }).click();
        await expect(reviewerPage).toHaveURL(/\/admin\/sellers/);
        await expect(
            reviewerPage.getByText('Admin Staff Portal').first(),
        ).toBeVisible();
        await expect(
            reviewerPage.getByRole('link', { name: 'My Orders' }),
        ).toHaveCount(0);

        await reviewerPage
            .getByRole('link', { name: 'Inspect' })
            .first()
            .click();
        await expect(reviewerPage).toHaveURL(/\/admin\/sellers\/\d+/);
        await expect(reviewerPage.getByText('government-id.png')).toBeVisible();
        await expect(
            reviewerPage.getByRole('button', { name: 'Approved' }),
        ).toHaveCount(0);

        await reviewerPage
            .getByRole('button', { name: 'Under Review' })
            .click();
        await expect(
            reviewerPage.getByText('Under Review').first(),
        ).toBeVisible();

        const forbiddenOrdersResp = await reviewerPage.goto(
            `${mv2BaseUrl}/admin/orders`,
        );
        expect(forbiddenOrdersResp?.status()).toBe(403);
        await reviewerContext.close();

        // 3. Super Admin logs in, reviews seller queue & approves store
        const adminContext = await browser.newContext({
            viewport: { width: 1280, height: 800 },
        });
        const adminPage = await adminContext.newPage();
        adminPage.on('pageerror', (err) => errors.push(err.message));

        await adminPage.goto(`${mv2BaseUrl}/login`, {
            waitUntil: 'networkidle',
        });
        await adminPage
            .getByLabel('Email address')
            .fill('superadmin.mv2@gmail.com');
        await adminPage
            .getByRole('textbox', { name: 'Password' })
            .fill('Mv2!Pass2026');
        await adminPage.getByRole('button', { name: 'Log in' }).click();
        await expect(adminPage).toHaveURL(/\/admin\/operations/);

        await adminPage.goto(`${mv2BaseUrl}/admin/sellers`, {
            waitUntil: 'networkidle',
        });
        await expect(
            adminPage.getByText('Super Admin Portal').first(),
        ).toBeVisible();
        await expect(
            adminPage.getByRole('link', { name: 'My Orders' }),
        ).toHaveCount(0);
        await expect(
            adminPage.getByText('Quezon Craft Audio').first(),
        ).toBeVisible();

        if (!process.env.CI && fs.existsSync(artifactsDir)) {
            await adminPage.screenshot({
                path: path.join(artifactsDir, 'mv2_admin_sellers_queue.png'),
            });
        }

        await adminPage.getByRole('link', { name: 'Inspect' }).first().click();
        await expect(adminPage).toHaveURL(/\/admin\/sellers\/\d+/);

        if (!process.env.CI && fs.existsSync(artifactsDir)) {
            await adminPage.screenshot({
                path: path.join(artifactsDir, 'mv2_admin_seller_detail.png'),
            });
        }

        await adminPage.getByRole('button', { name: 'Approved' }).click();
        await expect(adminPage.getByText('Approved').first()).toBeVisible();
        await adminContext.close();

        // 4. Approved Seller reloads / accesses /seller/dashboard cleanly (Desktop + Mobile)
        await sellerPage.goto(`${mv2BaseUrl}/seller/dashboard`, {
            waitUntil: 'networkidle',
        });
        await expect(sellerPage).toHaveURL(/\/seller\/dashboard/);
        await expect(
            sellerPage.getByText('Seller Portal').first(),
        ).toBeVisible();
        await expect(
            sellerPage.getByText('Verified Store').first(),
        ).toBeVisible();
        await expect(
            sellerPage.getByRole('link', { name: 'My Orders' }),
        ).toHaveCount(0);
        await expect(
            sellerPage.getByRole('heading', {
                level: 1,
                name: 'Quezon Craft Audio',
            }),
        ).toBeVisible();

        await sellerPage.setViewportSize({ width: 375, height: 812 });
        const sellerMobileOverflow = await sellerPage.evaluate(
            () =>
                document.documentElement.scrollWidth >
                document.documentElement.clientWidth,
        );
        expect(sellerMobileOverflow).toBe(false);
        await sellerPage.setViewportSize({ width: 1280, height: 800 });

        if (!process.env.CI && fs.existsSync(artifactsDir)) {
            await sellerPage.screenshot({
                path: path.join(artifactsDir, 'mv2_seller_dashboard.png'),
            });
        }

        await sellerContext.close();
        expect(errors).toEqual([]);
    });
});
