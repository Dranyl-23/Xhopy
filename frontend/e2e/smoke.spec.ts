import { expect, test } from '@playwright/test';

test.describe('XHopy foundation smoke checks', () => {
    for (const viewport of [
        { name: 'mobile', width: 375, height: 812 },
        { name: 'tablet', width: 768, height: 1024 },
        { name: 'desktop', width: 1280, height: 800 },
    ]) {
        test(`home page renders without horizontal overflow on ${viewport.name} (${viewport.width}px)`, async ({
            page,
        }) => {
            await page.setViewportSize({
                width: viewport.width,
                height: viewport.height,
            });

            const response = await page.goto('/');
            expect(response?.ok()).toBe(true);

            const hasOverflow = await page.evaluate(() => {
                return (
                    document.documentElement.scrollWidth >
                    document.documentElement.clientWidth
                );
            });

            expect(hasOverflow).toBe(false);
        });
    }
});
