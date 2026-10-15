import { type BrowserContext, test as base, expect } from '@playwright/test';

/**
 * Browser console noise that is expected and not a bug. Keep this list short.
 */
const IGNORED: RegExp[] = [
    // The kid PIN keypad and validation tests trigger these on purpose.
    /Failed to load resource: the server responded with a status of 4(09|22)/,
];

type ConsoleProblem = { page: string; type: string; text: string };

/**
 * Collects console errors/warnings and uncaught page errors from every page in a context.
 * Contexts created manually with browser.newContext() must be passed to watchConsole().
 */
export const test = base.extend<{ watchConsole: (context: BrowserContext) => void }>({
    watchConsole: [
        async ({ context }, use) => {
            const problems: ConsoleProblem[] = [];

            const watch = (ctx: BrowserContext) => {
                ctx.on('console', (msg) => {
                    if (msg.type() !== 'error' && msg.type() !== 'warning') return;
                    if (IGNORED.some((re) => re.test(msg.text()))) return;
                    problems.push({ page: msg.page()?.url() ?? '?', type: msg.type(), text: msg.text() });
                });
                ctx.on('weberror', (err) => problems.push({ page: err.page()?.url() ?? '?', type: 'pageerror', text: err.error().message }));
            };

            watch(context);
            await use(watch);

            expect(problems, `Browser console problems:\n${problems.map((p) => `[${p.type}] ${p.page}\n  ${p.text}`).join('\n')}`).toEqual([]);
        },
        { auto: true },
    ],
});

export { expect };
