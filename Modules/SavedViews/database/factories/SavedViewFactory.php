<?php

namespace Modules\SavedViews\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\SavedViews\Models\SavedView;

/**
 * @extends Factory<SavedView>
 */
class SavedViewFactory extends Factory
{
    protected $model = SavedView::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'resource' => 'pricing.prices',
            'name' => Str::title(fake()->unique()->words(2, true)),
            'filters' => [],
            'columns' => [],
        ];
    }

    public function forResource(string $resource): static
    {
        return $this->state(fn (): array => ['resource' => $resource]);
    }
}
