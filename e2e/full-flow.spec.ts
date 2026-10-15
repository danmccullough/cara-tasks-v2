import { expect, type Page, test } from '@playwright/test';
import { artisan, householdTimeFromNow, query } from './env';

/**
 * The whole loop in a real browser:
 * parent sets up kid, reward, chore and schedule -> reminder fires -> kid logs in with PIN and taps Done
 * -> parent approves -> kid gets points and redeems a reward -> parent hands it over.
 */
test('chore lifecycle from setup to reward', async ({ browser }, testInfo) => {
    test.setTimeout(90_000); // long multi-step flow against the single-threaded PHP dev server

    // Both projects share one DB, so names are unique per project.
    const suffix = testInfo.project.name;
    const kidName = `Sam-${suffix}`;
    const choreTitle = `Shower-${suffix}`;
    const rewardTitle = `Ice cream-${suffix}`;

    // Due in 10 minutes => inside the 15-minute reminder window right away.
    const due = householdTimeFromNow(10);
    test.skip(!due.sameDay, 'Too close to midnight for a same-day chore');

    const parentContext = await browser.newContext();
    const parent = await parentContext.newPage();
    const kidContext = await browser.newContext();
    const kid = await kidContext.newPage();

    await test.step('parent logs in', async () => {
        await parent.goto('/login');
        await parent.getByLabel('Email address').fill('parent@example.com');
        await parent.getByLabel('Password').fill('password');
        await parent.getByRole('button', { name: 'Log in' }).click();
        await expect(parent).toHaveURL(/\/dashboard$/);
        await expect(parent.getByRole('heading', { name: 'Waiting for approval' })).toBeVisible();
    });

    await test.step('parent adds a kid', async () => {
        await parent.goto('/family');
        await parent.getByRole('button', { name: 'Add kid' }).click();
        const dialog = parent.getByRole('dialog');
        await dialog.getByLabel('Name').fill(kidName);
        await dialog.getByLabel('Avatar').fill('🦖');
        await dialog.getByLabel('4-digit PIN').fill('1234');
        await dialog.getByRole('button', { name: 'emerald' }).click();
        await dialog.getByRole('button', { name: 'Save' }).click();
        await expect(dialog).toBeHidden();
        await expect(parent.getByText(kidName)).toBeVisible();
    });

    await test.step('parent adds a reward', async () => {
        await parent.goto('/rewards');
        await parent.getByRole('button', { name: 'Add reward' }).click();
        const dialog = parent.getByRole('dialog');
        await dialog.getByLabel('Emoji').fill('🍦');
        await dialog.getByLabel('Title').fill(rewardTitle);
        await dialog.getByLabel('Cost (points)').fill('5');
        await dialog.getByRole('button', { name: 'Save' }).click();
        await expect(dialog).toBeHidden();
        await expect(parent.getByText(rewardTitle)).toBeVisible();
    });

    await test.step('parent creates a chore and schedules it', async () => {
        await parent.goto('/chores');
        await parent.getByRole('button', { name: 'Add chore' }).click();
        let dialog = parent.getByRole('dialog');
        await dialog.getByLabel('Emoji').fill('🚿');
        await dialog.getByLabel('Title').fill(choreTitle);
        await dialog.getByLabel('Points').fill('5');
        await dialog.getByLabel(/Instructions/).fill('Wash hair too');
        await dialog.getByRole('button', { name: 'Save' }).click();
        await expect(dialog).toBeHidden();

        const card = parent.locator('div.rounded-xl', { hasText: choreTitle });
        await card.getByRole('button', { name: 'Assign' }).click();
        dialog = parent.getByRole('dialog');
        const kidId = await dialog.locator('#kid option', { hasText: kidName }).getAttribute('value');
        await dialog.getByLabel('Kid').selectOption(kidId!);
        await dialog.getByLabel('Due at').fill(due.time);
        await dialog.getByRole('button', { name: 'Save' }).click();
        await expect(dialog).toBeHidden();
        await expect(card.getByText(`${kidName} · Every day at ${due.time}`)).toBeVisible();
    });

    await test.step('scheduler sends the first reminder', async () => {
        artisan('reminders:dispatch');

        const rows = query<{ data: string }>(
            `select n.data from notifications n join users u on u.id = n.notifiable_id
             where u.name = ? and n.type = 'App\\Notifications\\ChoreReminder'`,
            kidName,
        );
        expect(rows).toHaveLength(1);
        expect(JSON.parse(rows[0].data).title).toBe(choreTitle);
    });

    await test.step('kid logs in with PIN and sees the chore under "Do now"', async () => {
        await kid.goto('/kid-login');
        await kid.getByRole('button', { name: kidName }).click();
        await expect(kid.getByRole('heading', { name: `Hi ${kidName}!` })).toBeVisible();
        for (const digit of '1234') await kid.getByRole('button', { name: digit, exact: true }).click();

        await expect(kid).toHaveURL(/\/today$/);
        await expect(kid.getByRole('heading', { name: 'Do now' })).toBeVisible();
        await expect(kid.getByText(choreTitle)).toBeVisible();
        await expect(kid.getByText('Wash hair too')).toBeVisible();
    });

    await test.step('kid taps Start and the parent sees it', async () => {
        await kid.getByRole('button', { name: '▶ Start' }).click();
        await expect(kid.getByText(/▶️ Started at/)).toBeVisible();
        await expect(kid.getByRole('button', { name: '▶ Start' })).toBeHidden();

        const started = query(
            `select 1 from notifications where type = 'App\\Notifications\\ChoreStarted' and data like ?`,
            `%${kidName} started: ${choreTitle}%`,
        );
        expect(started.length).toBeGreaterThan(0);

        await parent.goto('/dashboard');
        await expect(parent.locator('div.rounded-xl', { hasText: kidName }).getByText(/\(started /)).toBeVisible();
    });

    await test.step('kid taps Done', async () => {
        await kid.getByRole('button', { name: '✓ Done' }).click();
        await expect(kid.getByText('🎉 Nice job!')).toBeVisible();
        await expect(kid.getByText('waiting for OK')).toBeVisible();
        await expect(kid.getByRole('heading', { name: 'Do now' })).toBeHidden();
    });

    await test.step('parent was notified and approves', async () => {
        const submitted = query(
            `select 1 from notifications where type = 'App\\Notifications\\ChoreSubmitted' and data like ?`,
            `%${kidName} finished: ${choreTitle}%`,
        );
        expect(submitted.length).toBeGreaterThan(0);

        await parent.goto('/dashboard');
        const row = parent.locator('div.rounded-xl', { hasText: `${kidName}: ${choreTitle}` });
        await row.getByRole('button', { name: 'Approve' }).click();
        await expect(row).toBeHidden();
        await expect(parent.locator('div.rounded-xl', { hasText: kidName }).getByText('1/1 done · ⭐ 5')).toBeVisible();
    });

    await test.step('kid sees the points and redeems the reward', async () => {
        await kid.reload();
        await expect(kid.getByText('⭐ +5')).toBeVisible();
        await expect(kid.getByText('🌟 All done for today!')).toBeVisible();

        await kid.getByRole('link', { name: '🎁 Rewards' }).click();
        const reward = kid.locator('div.rounded-2xl', { hasText: rewardTitle });
        kid.once('dialog', (d) => d.accept());
        await reward.getByRole('button', { name: 'Get it!' }).click();
        await expect(kid.getByRole('heading', { name: 'Waiting for a grown-up' })).toBeVisible();
        await expect(kid.locator('header')).toContainText('⭐ 0');
    });

    await test.step('parent hands over the reward', async () => {
        await parent.goto('/dashboard');
        const request = parent.locator('div.rounded-xl', { hasText: `${kidName}: ${rewardTitle}` });
        await request.getByRole('button', { name: 'Given' }).click();
        await expect(request).toBeHidden();
    });

    await test.step('kid switches user back to the picker', async () => {
        await kid.reload();
        await expect(kid.getByRole('heading', { name: 'Waiting for a grown-up' })).toBeHidden();
        await kid.getByRole('button', { name: 'Switch user' }).click();
        await expect(kid).toHaveURL(/\/kid-login$/);
    });

    await parentContext.close();
    await kidContext.close();
});

test('kid login guards', async ({ page }, testInfo) => {
    const kidName = `Guard-${testInfo.project.name}`;

    await loginAsParent(page);
    await page.goto('/family');
    await page.getByRole('button', { name: 'Add kid' }).click();
    await page.getByRole('dialog').getByLabel('Name').fill(kidName);
    await page.getByRole('dialog').getByLabel('4-digit PIN').fill('4321');
    await page.getByRole('dialog').getByRole('button', { name: 'Save' }).click();
    await expect(page.getByText(kidName)).toBeVisible();
    await page.context().clearCookies();

    await test.step('wrong PIN is rejected', async () => {
        await page.goto('/kid-login');
        await page.getByRole('button', { name: kidName }).click();
        for (const digit of '0000') await page.getByRole('button', { name: digit, exact: true }).click();
        await expect(page.getByText('That PIN is not right. Try again.')).toBeVisible();
        await expect(page).toHaveURL(/\/kid-login$/);
    });

    await test.step('kid cannot open parent pages', async () => {
        for (const digit of '4321') await page.getByRole('button', { name: digit, exact: true }).click();
        await expect(page).toHaveURL(/\/today$/);
        await page.goto('/chores');
        await expect(page).toHaveURL(/\/today$/);
    });
});

async function loginAsParent(page: Page) {
    await page.goto('/login');
    await page.getByLabel('Email address').fill('parent@example.com');
    await page.getByLabel('Password').fill('password');
    await page.getByRole('button', { name: 'Log in' }).click();
    await expect(page).toHaveURL(/\/dashboard$/);
}
