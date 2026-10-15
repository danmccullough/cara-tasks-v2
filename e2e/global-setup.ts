import fs from 'node:fs';
import { artisan, serverEnv } from './env';

/** Fresh SQLite database with only a parent account. */
export default function globalSetup() {
    fs.writeFileSync(serverEnv.DB_DATABASE, '');
    artisan('migrate:fresh --seeder=Database\\\\Seeders\\\\E2eSeeder --seed --force');
}
