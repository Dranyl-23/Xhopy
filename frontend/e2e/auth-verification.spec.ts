import { execFileSync, spawn, type ChildProcess } from 'node:child_process';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import process from 'node:process';
import { expect, test } from '@playwright/test';

const backendRoot = path.resolve(import.meta.dirname, '../../backend');
const isolatedDbPath = path.join(
    backendRoot,
    'storage/framework/playwright-auth-e2e.sqlite',
);
const authPort = process.env.PLAYWRIGHT_AUTH_PORT ?? '18082';
const authBaseUrl = `http://127.0.0.1:${authPort}`;

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
let capturedStderr = '';

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

async function waitForVerificationCode(startOffset: number): Promise<string> {
    const deadline = Date.now() + 10_000;
    while (Date.now() < deadline) {
        const chunk = capturedStderr.slice(startOffset).replace(/=\r?\n/g, '');
        if (chunk.includes('email verification code')) {
            const strongMatch = chunk.match(/<strong[^>]*>(\d{6})<\/strong>/);
            if (strongMatch?.[1]) {
                return strongMatch[1];
            }

            const boldMatch = chunk.match(/\*\*(\d{6})\*\*/);
            if (boldMatch?.[1]) {
                return boldMatch[1];
            }
        }
        await new Promise((r) => setTimeout(r, 150));
    }
    throw new Error(
        `Timed out waiting for 6-digit verification code in stderr log.\nCaptured:\n${capturedStderr.slice(startOffset)}`,
    );
}

test.describe.serial('Gmail Email Verification Code Flow', () => {
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
            APP_URL: authBaseUrl,
            MAIL_MAILER: 'log',
            MAIL_LOG_CHANNEL: 'stderr',
            VERIFICATION_MAILER: 'log',
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

        serverProcess = spawn(
            phpCmd,
            [
                ...phpExtensions,
                '-S',
                `127.0.0.1:${authPort}`,
                '../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php',
            ],
            {
                cwd: path.join(backendRoot, 'public'),
                env: serverEnv,
                stdio: ['ignore', 'ignore', 'pipe'],
            },
        );

        serverProcess.stderr?.on('data', (data: Buffer) => {
            capturedStderr += data.toString('utf8');
        });

        await waitForServer(authBaseUrl);
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

    test('registered Gmail user requests and verifies a 6-digit code cleanly on desktop and mobile', async ({
        page,
    }) => {
        const errors: string[] = [];
        page.on('pageerror', (err) => errors.push(err.message));
        page.on('console', (msg) => {
            if (msg.type() === 'error') {
                errors.push(msg.text());
            }
        });

        await page.setViewportSize({ width: 375, height: 812 });
        await page.goto(`${authBaseUrl}/register`, {
            waitUntil: 'networkidle',
        });

        await page.getByLabel('Name').fill('Juan Dela Cruz');
        await page.getByLabel('Email address').fill('juan.delacruz@gmail.com');
        await page
            .getByLabel('Password', { exact: true })
            .fill('Str0ng!Pass#2026');
        await page.getByLabel('Confirm password').fill('Str0ng!Pass#2026');

        await page.getByRole('button', { name: 'Create account' }).click();

        await expect(page).toHaveURL(`${authBaseUrl}/email/verify`);
        await expect(
            page.getByText('Gmail verification address:'),
        ).toBeVisible();
        await expect(page.getByText('juan.delacruz@gmail.com')).toBeVisible();

        const mobileOverflow = await page.evaluate(
            () =>
                document.documentElement.scrollWidth >
                document.documentElement.clientWidth,
        );
        expect(mobileOverflow).toBe(false);

        const logOffsetBeforeSend = capturedStderr.length;
        await page
            .getByRole('button', { name: 'Send verification code to Gmail' })
            .click();

        await expect(
            page.getByText(/A 6-digit verification code has been sent to/),
        ).toBeVisible();

        const issuedCode = await waitForVerificationCode(logOffsetBeforeSend);

        await page.setViewportSize({ width: 1280, height: 800 });

        const otpInput = page.locator('#verification-code');
        await otpInput.fill('000000');
        await page.getByRole('button', { name: 'Verify code' }).click();
        await expect(
            page.getByText('The verification code you entered is invalid.'),
        ).toBeVisible();

        await otpInput.fill(issuedCode);
        await page.getByRole('button', { name: 'Verify code' }).click();

        await expect(page).toHaveURL(/\/dashboard/);
        expect(errors).toEqual([]);
    });
});
