<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Central DB seeder — tenant data goes through TenantSeeder.
        // Run on tenant DBs with: php artisan tenants:seed --class=TenantSeeder
    }
}
