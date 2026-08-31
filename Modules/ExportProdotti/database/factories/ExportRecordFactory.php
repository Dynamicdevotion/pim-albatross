<?php

namespace Modules\ExportProdotti\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\ExportProdotti\Models\ExportRecord;

/**
 * @extends Factory<ExportRecord>
 */
class ExportRecordFactory extends Factory
{
    protected $model = ExportRecord::class;

    public function definition(): array
    {
        return [
            'user_id' => null,
            'format' => 'xlsx',
            'columns' => ['sku', 'name', 'price', 'stock', 'status'],
            'filters' => [],
            'sort' => null,
            'status' => 'pending',
            'total_rows' => null,
            'row_count' => null,
            'stored_path' => null,
            'original_filename' => 'export-prodotti-'.now()->format('Ymd-His').'.xlsx',
            'error_message' => null,
            'started_at' => null,
            'finished_at' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (): array => [
            'status' => 'completed',
            'total_rows' => 3,
            'row_count' => 3,
            'stored_path' => 'exports/'.$this->faker->uuid().'.xlsx',
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
        ]);
    }
}
