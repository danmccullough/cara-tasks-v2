import AxeBuilder from '@axe-core/playwright';
import type { Page } from '@playwright/test';
import { artisan, query } from './env';
import { expect, test } from './fixtures';

/**
 * WCAG 2.2 AA scan (axe-core) of every page and dialog, in light and dark mode.
 * Uses the demo family from DatabaseSeeder: mom@example.com / password, kid "Sam" PIN 1234.
 */
const WCAG_TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22a', 'wcag22aa'];

test.beforeAll(() => {
    if (query("select 1 from users where email = 'mom@example.com'").length === 0) {
        artisan('db:seed --class=DatabaseSeeder --force');
    }
});

async function expectAccessible(page: Page, label: string) {
    for (const colorScheme of ['light', 'dark'] as const) {
        await page.emulateMedia({ colorScheme });
        // Let the theme class and transitions settle before measuring contrast.
        await page.waitForTimeout(250);

        const results = await new AxeBuilder({ page }).withTags(WCAG_TAGS).analyze();
        // One line per failing element keeps the report readable.
        const problems = results.violations.flatMap((v) =>
            v.nodes.map((n) => `${v.id}: ${n.target.join(' ')} -> ${n.failureSummary?.split('\n')[1]?.trim()}`),
        );

        expect(problems, `${label} [${colorScheme}] WCAG violations`).toEqual([]);
    }
}

async function login(page: Page, email: string) {
    await page.goto('/login');
    await page.getByLabel('Email address').fill(email);
    await page.getByLabel('Password').fill('password');
    await page.getByRole('button', { name: 'Log in' }).click();
    await expect(page).toHaveURL(/\/dashboard$/);
}

test('guest pages', async ({ page }) => {
    for (const url of ['/login', '/forgot-password', '/kid-login']) {
        await page.goto(url);
        await expectAccessible(page, url);
    }

    await page.getByRole('button', { name: 'Sam', exact: true }).click();
    await expect(page.getByRole('heading', { name: 'Hi Sam!' })).toBeVisible();
    await expectAccessible(page, 'kid PIN pad');
});

test('kid pages', async ({ page }) => {
    await page.goto('/kid-login');
    await page.getByRole('button', { name: 'Sam', exact: true }).click();
    for (const digit of '1234') await page.getByRole('button', { name: digit, exact: true }).click();
    await expect(page).toHaveURL(/\/today$/);

    await expectAccessible(page, '/today');

    await page.goto('/reward-shop');
    await expectAccessible(page, '/reward-shop');
});

test('parent pages and dialogs', async ({ page, isMobile }) => {
    test.setTimeout(120_000); // 13 scans x light/dark against the single-threaded PHP dev server
    await login(page, 'mom@example.com');

    for (const url of ['/dashboard', '/chores', '/rewards', '/family', '/settings/profile', '/settings/password', '/settings/appearance']) {
        await page.goto(url);
        await expectAccessible(page, url);
    }

    const dialogs: { url: string; open: () => Promise<void>; label: string }[] = [
        { url: '/chores', label: 'new chore dialog', open: () => page.getByRole('button', { name: 'Add chore' }).click() },
        { url: '/chores', label: 'schedule dialog', open: () => page.getByRole('button', { name: 'Assign' }).first().click() },
        { url: '/rewards', label: 'reward dialog', open: () => page.getByRole('button', { name: 'Add reward' }).click() },
        { url: '/family', label: 'add kid dialog', open: () => page.getByRole('button', { name: 'Add kid' }).click() },
        { url: '/family', label: 'add parent dialog', open: () => page.getByRole('button', { name: 'Add parent' }).click() },
    ];
    for (const dialog of dialogs) {
        await page.goto(dialog.url);
        await dialog.open();
        await expect(page.getByRole('dialog')).toBeVisible();
        await expectAccessible(page, dialog.label);
    }

    if (isMobile) {
        await page.goto('/dashboard');
        await page.getByRole('button', { name: 'Toggle Sidebar' }).click();
        await expect(page.getByRole('dialog')).toBeVisible();
        await expectAccessible(page, 'mobile navigation menu');
    }
});
