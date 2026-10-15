import { defineConfig } from '@playwright/test';
import base from './playwright.config';

/** `npm run lighthouse`: production build + Lighthouse audits (not part of the regular e2e run). */
export default defineConfig({
    ...base,
    testMatch: /.*\.audit\.ts$/,
    projects: [{ name: 'lighthouse' }],
});
