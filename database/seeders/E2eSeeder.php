<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Bare minimum for Playwright: one parent. Everything else is created through the UI.
 */
class E2eSeeder extends Seeder
{
    public function run(): void
    {
        User::factory()->create(['name' => 'E2E Parent', 'email' => 'parent@example.com']);
    }
}
