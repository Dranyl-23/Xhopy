import { existsSync } from 'node:fs';
import process from 'node:process';
import { defineConfig, devices } from '@playwright/test';

const port = process.env.PLAYWRIGHT_PORT ?? '18080';
const baseURL = process.env.PLAYWRIGHT_BASE_URL ?? `http://127.0.0.1:${port}`;

const phpBinary =
    process.platform === 'win32' && existsSync('C:\\php85\\php.exe')
        ? '"C:\\php85\\php.exe" -d extension=C:\\php85\\ext\\php_pdo_sqlite.dll -d extension=C:\\php85\\ext\\php_sodium.dll'
        : 'php';

const webServerCommand =
    process.env.PLAYWRIGHT_WEB_COMMAND ??
    `${phpBinary} -S 127.0.0.1:${port} ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php`;

export default defineConfig({
    testDir: './e2e',
    fullyParallel: true,
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 2 : 0,
    workers: process.env.CI ? 1 : undefined,
    reporter: 'list',
    use: {
        baseURL,
        trace: 'on-first-retry',
    },
    projects: [
        {
            name: 'chromium',
            use: {
                ...devices['Desktop Chrome'],
                ...(process.env.CI ? {} : { channel: 'chrome' }),
            },
        },
    ],
    webServer: process.env.PLAYWRIGHT_SKIP_WEBSERVER
        ? undefined
        : {
              command: webServerCommand,
              cwd: '../backend/public',
              url: baseURL,
              reuseExistingServer: false,
              timeout: 60_000,
          },
});
