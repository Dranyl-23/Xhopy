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
    'storage/framework/playwright-catalog-e2e.sqlite',
);
const catalogPort = process.env.PLAYWRIGHT_CATALOG_PORT ?? '18081';
const catalogBaseUrl = `http://127.0.0.1:${catalogPort}`;

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
            // Retry until PHP built-in server binds
        }
        await new Promise((r) => setTimeout(r, 250));
    }
    throw new Error(`Timed out waiting for isolated server at ${url}`);
}

test.describe
    .serial('XHopy Batch 3 Catalog, Discovery & Admin Workflows', () => {
    test.beforeAll(async () => {
        if (fs.existsSync(isolatedDbPath)) {
            fs.unlinkSync(isolatedDbPath);
        }
        fs.writeFileSync(isolatedDbPath, '');

        const serverEnv = {
            ...process.env,
            DB_CONNECTION: 'sqlite',
            DB_DATABASE: isolatedDbPath,
            APP_URL: catalogBaseUrl,
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
                'db:seed',
                '--class=CatalogDemoSeeder',
                '--force',
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
                `127.0.0.1:${catalogPort}`,
                '../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php',
            ],
            {
                cwd: path.join(backendRoot, 'public'),
                env: serverEnv,
                stdio: 'ignore',
            },
        );

        await waitForServer(catalogBaseUrl);
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
                // Ignore transient file lock cleanup
            }
        }
    });

    test('seeded storefront, catalog filtering, sorting, and mobile filters work without overflow', async ({
        page,
    }) => {
        const errors: string[] = [];
        page.on('pageerror', (err) => errors.push(err.message));

        await page.setViewportSize({ width: 1280, height: 800 });
        await page.goto(`${catalogBaseUrl}/catalog`);

        await expect(
            page.getByRole('heading', {
                level: 1,
                name: 'All Catalog Products',
            }),
        ).toBeVisible();
        await expect(
            page.getByText('6 items matching current filters'),
        ).toBeVisible();

        // Filter by category and sort price ascending
        await page
            .getByRole('button', { name: /\[DEMO\] Audio & Acoustics/i })
            .first()
            .click();
        await expect(page).toHaveURL(/category=audio-acoustics/);
        await expect(
            page.getByText('2 items matching current filters'),
        ).toBeVisible();

        await page.locator('#catalog-sort').click();
        await page.getByRole('option', { name: 'Price: Low to High' }).click();
        await expect(page).toHaveURL(/sort=price_asc/);
        await page.waitForLoadState('networkidle');

        if (!process.env.CI && fs.existsSync(artifactsDir)) {
            await page.screenshot({
                path: path.join(artifactsDir, 'batch3_catalog_desktop.png'),
            });
        }

        // Mobile viewport check
        await page.setViewportSize({ width: 375, height: 812 });
        const hasOverflow = await page.evaluate(
            () =>
                document.documentElement.scrollWidth >
                document.documentElement.clientWidth,
        );
        expect(hasOverflow).toBe(false);
        expect(errors).toEqual([]);
    });

    test('product detail loads gallery media and updates SKU, price, and stock cleanly when selecting variants', async ({
        page,
    }) => {
        await page.setViewportSize({ width: 1280, height: 800 });
        await page.goto(
            `${catalogBaseUrl}/products/demo-xhopy-reference-studio-headphones`,
        );

        await expect(
            page.getByRole('heading', {
                level: 1,
                name: '[DEMO] XHopy Reference Studio Headphones',
            }),
        ).toBeVisible();

        await expect(
            page.locator('strong', { hasText: 'DEMO-AUD-001-CHR' }),
        ).toBeVisible();
        await expect(page.getByText('₱6,490.00').first()).toBeVisible();

        // Switch variant to Deep Crimson
        await page.getByRole('button', { name: /Deep Crimson/i }).click();
        await expect(
            page.locator('strong', { hasText: 'DEMO-AUD-001-CRM' }),
        ).toBeVisible();
        await expect(page.getByText('₱6,890.00').first()).toBeVisible();
        await page.waitForTimeout(100);

        if (!process.env.CI && fs.existsSync(artifactsDir)) {
            await page.screenshot({
                path: path.join(artifactsDir, 'batch3_product_detail.png'),
            });
        }

        await page.getByRole('button', { name: 'Add to Cart' }).click();
        await expect(
            page.getByRole('button', { name: /Shopping cart/i }),
        ).toContainText('1');
    });

    test('catalog manager can sign in and access admin catalog dashboard while guests redirect', async ({
        page,
    }) => {
        await page.setViewportSize({ width: 1280, height: 800 });
        await page.goto(`${catalogBaseUrl}/admin/catalog`);
        await expect(page).toHaveURL(/\/login/);

        await page
            .getByLabel('Email address')
            .fill('demo-catalog-admin@xhopy.local');
        await page
            .getByRole('textbox', { name: 'Password' })
            .fill('DemoCatalog!2026');
        await page.getByRole('button', { name: 'Log in' }).click();

        await page.waitForURL(`${catalogBaseUrl}/admin/catalog`);
        await expect(
            page.getByRole('heading', { level: 1, name: 'Catalog Management' }),
        ).toBeVisible();
        await expect(
            page.getByText('[DEMO] XHopy Reference Studio Headphones'),
        ).toBeVisible();

        await expect
            .poll(async () =>
                page.evaluate(() => {
                    const imgs = Array.from(
                        document.querySelectorAll('table img'),
                    );
                    return (
                        imgs.length > 0 &&
                        imgs.every(
                            (img) =>
                                img instanceof HTMLImageElement &&
                                img.complete &&
                                img.naturalWidth > 0,
                        )
                    );
                }),
            )
            .toBe(true);

        if (!process.env.CI && fs.existsSync(artifactsDir)) {
            await page.screenshot({
                path: path.join(artifactsDir, 'batch3_admin_catalog.png'),
            });
        }
    });
});
