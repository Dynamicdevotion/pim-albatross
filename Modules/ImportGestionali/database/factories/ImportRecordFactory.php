<?php

namespace Modules\ImportGestionali\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\ImportGestionali\Models\ImportRecord;

/**
 * @extends Factory<ImportRecord>
 */
class ImportRecordFactory extends Factory
{
    protected $model = ImportRecord::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'original_filename' => 'listino.csv',
            'stored_path' => null,
            'status' => 'completed',
            'update_existing' => false,
            'create_missing_terms' => false,
            'replace_taxonomy_terms' => false,
            'mapping' => [0 => 'sku', 1 => 'name', 2 => 'price'],
            'meta' => ['header' => ['Codice', 'Nome', 'Prezzo'], 'delimiter' => ';', 'encoding' => null],
            'total_rows' => 3,
            'created_count' => 3,
            'updated_count' => 0,
            'skipped_count' => 0,
            'issues' => [],
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
        ];
    }

    public function running(): static
    {
        return $this->state(fn (): array => [
            'status' => 'processing',
            'finished_at' => null,
        ]);
    }
}
