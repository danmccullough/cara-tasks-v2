import { execSync } from 'node:child_process';
import path from 'node:path';
import { DatabaseSync } from 'node:sqlite';
import { fileURLToPath } from 'node:url';

export const PORT = 8123;
export const BASE_URL = `http://127.0.0.1:${PORT}`;
export const TIMEZONE = 'America/New_York';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');

/** Env for the E2E Laravel server and artisan calls. Real env vars override .env, so the dev DB is never touched. */
export const serverEnv: Record<string, string> = {
    APP_ENV: 'local',
    APP_DEBUG: 'true',
    APP_URL: BASE_URL,
    DB_CONNECTION: 'sqlite',
    DB_DATABASE: path.join(root, 'database', 'e2e.sqlite'),
    SESSION_DRIVER: 'file',
    CACHE_STORE: 'file',
    QUEUE_CONNECTION: 'sync',
    MAIL_MAILER: 'log',
    HOUSEHOLD_TIMEZONE: TIMEZONE,
    VITE_HOT_FILE: path.join(root, 'storage', 'framework', 'e2e-no-hot'), // never exists: always use public/build
};

export function artisan(command: string): string {
    return execSync(`php artisan ${command}`, { cwd: root, env: { ...process.env, ...serverEnv }, encoding: 'utf8' });
}

/** Read-only query against the E2E database. */
export function query<T = Record<string, unknown>>(sql: string, ...params: (string | number)[]): T[] {
    const db = new DatabaseSync(serverEnv.DB_DATABASE, { readOnly: true });
    try {
        return db.prepare(sql).all(...params) as T[];
    } finally {
        db.close();
    }
}

/** "HH:MM" in the household timezone, `minutes` from now. */
export function householdTimeFromNow(minutes: number): { time: string; sameDay: boolean } {
    const fmt = (d: Date, opts: Intl.DateTimeFormatOptions) => new Intl.DateTimeFormat('en-GB', { timeZone: TIMEZONE, ...opts }).format(d);
    const now = new Date();
    const later = new Date(now.getTime() + minutes * 60_000);
    return {
        time: fmt(later, { hour: '2-digit', minute: '2-digit', hourCycle: 'h23' }),
        sameDay: fmt(now, { dateStyle: 'short' }) === fmt(later, { dateStyle: 'short' }),
    };
}
