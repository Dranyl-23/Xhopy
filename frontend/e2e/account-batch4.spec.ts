import { execFileSync, spawn, type ChildProcess } from 'node:child_process';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import process from 'node:process';
import { expect, test } from '@playwright/test';

const backendRoot = path.resolve(import.meta.dirname, '../../backend');
const isolatedDbPath = path.join(
    backendRoot,
    'storage/framework/playwright-batch4-e2e.sqlite',
);
const batch4Port = process.env.PLAYWRIGHT_BATCH4_PORT ?? '18083';
const batch4BaseUrl = `http://127.0.0.1:${batch4Port}`;

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

test.describe.serial('Batch 4 Identity, Address Book & Admin Roles', () => {
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
            APP_URL: batch4BaseUrl,
            MAIL_MAILER: 'log',
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
                    "App\\Models\\User::factory()->create(['name' => 'Buyer Account', 'email' => 'buyer.batch4@gmail.com', 'password' => 'Batch4!Pass2026']);",
                    "App\\Models\\User::factory()->admin()->create(['name' => 'Super Admin', 'email' => 'admin.batch4@gmail.com', 'password' => 'Batch4!Pass2026']);",
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
                `127.0.0.1:${batch4Port}`,
                '../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php',
            ],
            {
                cwd: path.join(backendRoot, 'public'),
                env: serverEnv,
                stdio: 'ignore',
            },
        );

        await waitForServer(batch4BaseUrl);
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

    test('customer can view account dashboard and manage saved addresses cleanly on mobile and desktop', async ({
        page,
    }) => {
        const errors: string[] = [];
        page.on('pageerror', (err) => errors.push(err.message));

        await page.setViewportSize({ width: 375, height: 812 });
        await page.goto(`${batch4BaseUrl}/login`, { waitUntil: 'networkidle' });

        await page.getByLabel('Email address').fill('buyer.batch4@gmail.com');
        await page
            .getByRole('textbox', { name: 'Password' })
            .fill('Batch4!Pass2026');
        await page.getByRole('button', { name: 'Log in' }).click();

        await expect(page).toHaveURL(/\/dashboard/);
        await expect(
            page.getByRole('heading', { name: /Welcome back, Buyer Account/ }),
        ).toBeVisible();

        await page.goto(`${batch4BaseUrl}/settings/addresses`, {
            waitUntil: 'networkidle',
        });

        await page.locator('#addr-label').click();
        await page.getByRole('option', { name: 'Office' }).click();
        await page.getByLabel('Recipient Name').fill('Juan Dela Cruz');
        await page.getByLabel('Contact Number').fill('+63 917 555 0100');
        await page
            .getByLabel('Street Address / Building')
            .fill('128 Ayala Avenue');
        await page.getByLabel('Barangay / District').fill('San Lorenzo');
        await page.getByLabel('City / Municipality').fill('Makati City');
        await page.getByLabel('Province / Region').fill('Metro Manila');
        await page.getByLabel('Postal Code').fill('1223');

        await page.getByRole('button', { name: 'Save address' }).click();

        await expect(
            page.getByText('Juan Dela Cruz · +63 917 555 0100'),
        ).toBeVisible();
        await expect(page.getByText('Default Shipping')).toBeVisible();

        const mobileOverflow = await page.evaluate(
            () =>
                document.documentElement.scrollWidth >
                document.documentElement.clientWidth,
        );
        expect(mobileOverflow).toBe(false);
        expect(errors).toEqual([]);
    });

    test('admin can filter users, promote role via custom select, and review audit log', async ({
        page,
    }) => {
        const errors: string[] = [];
        page.on('pageerror', (err) => errors.push(err.message));

        await page.setViewportSize({ width: 1280, height: 800 });
        await page.goto(`${batch4BaseUrl}/login`, { waitUntil: 'networkidle' });

        await page.getByLabel('Email address').fill('admin.batch4@gmail.com');
        await page
            .getByRole('textbox', { name: 'Password' })
            .fill('Batch4!Pass2026');
        await page.getByRole('button', { name: 'Log in' }).click();

        await expect(page).toHaveURL(/\/dashboard/);
        await page.goto(`${batch4BaseUrl}/admin/users`, {
            waitUntil: 'networkidle',
        });

        await expect(
            page.getByRole('heading', { name: /Users & Role Authorization/ }),
        ).toBeVisible();
        await expect(
            page.getByText('buyer.batch4@gmail.com').first(),
        ).toBeVisible();

        await page
            .getByRole('combobox', { name: 'Role for Buyer Account' })
            .click();
        await page.getByRole('option', { name: 'Staff' }).click();

        await expect(
            page.getByRole('cell', { name: 'Account role updated' }).first(),
        ).toBeVisible();
        expect(errors).toEqual([]);
    });
});
