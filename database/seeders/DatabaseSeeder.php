<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Modules\Localization\Database\Seeders\LocalizationDatabaseSeeder;
use Modules\Taxonomies\Database\Seeders\TaxonomiesDatabaseSeeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $this->call([
            // Localization first: it seeds the languages the others rely on.
            LocalizationDatabaseSeeder::class,
            TaxonomiesDatabaseSeeder::class,
        ]);
    }
}
