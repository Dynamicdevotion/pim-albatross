<?php

namespace Modules\Taxonomies\Database\Seeders;

use Illuminate\Database\Seeder;

class TaxonomiesDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call(TaxonomySeeder::class);
    }
}
