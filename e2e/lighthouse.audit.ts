import { chromium, expect, type Page, test } from '@playwright/test';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import lighthouse from 'lighthouse';
import desktopConfig from 'lighthouse/core/config/desktop-config.js';
import { artisan, BASE_URL, query } from './env';

/**
 * Lighthouse audit of the main pages against the production build.
 * Run with `npm run lighthouse`. Logs in first; Lighthouse reuses the browser session.
 *
 * By default it audits the PHP dev server, which has no gzip or cache headers, so performance is
 * reported but not enforced. Set LIGHTHOUSE_BASE_URL to a running Docker container (nginx) to
 * enforce performance too.
 */
const PORT = 9333;
const TARGET = process.env.LIGHTHOUSE_BASE_URL ?? BASE_URL;
const MIN: Record<string, number> = {
    accessibility: 1,
    'best-practices': 1,
    seo: 1,
    ...(process.env.LIGHTHOUSE_BASE_URL ? { performance: 0.9 } : {}),
};

test.setTimeout(10 * 60_000);

test.beforeAll(() => {
    if (query("select 1 from users where email = 'mom@example.com'").length === 0) {
        artisan('db:seed --class=DatabaseSeeder --force');
    }
});

async function audit(path: string, mode: 'mobile' | 'desktop') {
    const result = await lighthouse(
        `${TARGET}${path}`,
        { port: PORT, output: 'json', logLevel: 'error', disableStorageReset: true },
        mode === 'desktop' ? desktopConfig : undefined,
    );
    const lhr = result!.lhr;
    const scores = Object.fromEntries(Object.entries(lhr.categories).map(([id, c]) => [id, c.score ?? 0]));

    const failing = Object.values(lhr.audits)
        .filter((a) => a.score !== null && a.score < 0.9 && a.scoreDisplayMode !== 'informative' && a.scoreDisplayMode !== 'notApplicable')
        .map((a) => `    - ${a.id}: ${a.title}${a.displayValue ? ` (${a.displayValue})` : ''}`);

    console.log(
        `${mode.padEnd(7)} ${path.padEnd(16)} ` +
            Object.entries(scores)
                .map(([id, s]) => `${id}=${Math.round(s * 100)}`)
                .join('  ') +
            (failing.length ? `\n${failing.join('\n')}` : ''),
    );
    return scores;
}

async function login(page: Page) {
    await page.goto(`${TARGET}/login`);
    await page.getByLabel('Email address').fill('mom@example.com');
    await page.getByLabel('Password').fill('password');
    await page.getByRole('button', { name: 'Log in' }).click();
    await expect(page).toHaveURL(/\/dashboard$/);
}

async function loginKid(page: Page) {
    await page.goto(`${TARGET}/kid-login`);
    await page.getByRole('button', { name: 'Sam', exact: true }).click();
    for (const digit of '1234') await page.getByRole('button', { name: digit, exact: true }).click();
    await expect(page).toHaveURL(/\/today$/);
}

test('lighthouse scores', async () => {
    const failures: string[] = [];
    const check = (label: string, scores: Record<string, number>) => {
        for (const [id, min] of Object.entries(MIN)) {
            if ((scores[id] ?? 0) < min) failures.push(`${label}: ${id} ${Math.round((scores[id] ?? 0) * 100)} < ${min * 100}`);
        }
    };

    for (const mode of ['mobile', 'desktop'] as const) {
        // Persistent (default) context, so Lighthouse's tab shares the logged-in session.
        const profile = fs.mkdtempSync(path.join(os.tmpdir(), 'cara-lh-'));
        const browser = await chromium.launchPersistentContext(profile, { args: [`--remote-debugging-port=${PORT}`] });
        const page = browser.pages()[0] ?? (await browser.newPage());

        check(`${mode} /login`, await audit('/login', mode));
        check(`${mode} /kid-login`, await audit('/kid-login', mode));

        await loginKid(page);
        check(`${mode} /today`, await audit('/today', mode));
        check(`${mode} /reward-shop`, await audit('/reward-shop', mode));
        await page.getByRole('button', { name: 'Switch user' }).click();
        await expect(page).toHaveURL(/\/kid-login$/); // logout must finish before the parent logs in

        await login(page);
        for (const path of ['/dashboard', '/chores', '/family']) check(`${mode} ${path}`, await audit(path, mode));

        await browser.close();
        fs.rmSync(profile, { recursive: true, force: true });
    }

    expect(failures).toEqual([]);
});
