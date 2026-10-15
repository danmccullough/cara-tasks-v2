import { defineConfig, devices } from '@playwright/test';
import { BASE_URL, PORT, serverEnv, TIMEZONE } from './e2e/env';

export default defineConfig({
    testDir: './e2e',
    globalSetup: './e2e/global-setup.ts',
    fullyParallel: false,
    workers: 1, // tests share one database
    reporter: 'list',
    use: {
        baseURL: BASE_URL,
        timezoneId: TIMEZONE,
        trace: 'retain-on-failure',
    },
    projects: [
        { name: 'desktop', use: { ...devices['Desktop Chrome'] } },
        { name: 'phone', use: { ...devices['Pixel 7'] } },
    ],
    webServer: {
        // Plain PHP server (not `artisan serve`) so the env below reaches Laravel unchanged.
        command: `php -S 127.0.0.1:${PORT} -t public vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php`,
        url: `${BASE_URL}/up`,
        env: serverEnv,
        reuseExistingServer: false,
    },
});
