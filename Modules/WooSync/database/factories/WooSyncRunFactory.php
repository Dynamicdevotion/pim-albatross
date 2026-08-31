<?php

namespace Modules\WooSync\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\WooSync\Models\WooSyncRun;

/**
 * @extends Factory<WooSyncRun>
 */
class WooSyncRunFactory extends Factory
{
    protected $model = WooSyncRun::class;

    public function definition(): array
    {
        return [
            'trigger' => 'single',
            'status' => 'pending',
            'product_ids' => [],
            'total' => 0,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (): array => [
            'status' => 'completed',
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
        ]);
    }
}
