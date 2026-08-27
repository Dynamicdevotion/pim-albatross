<?php

namespace Modules\SavedViews\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Component;
use Livewire\Livewire;
use Modules\SavedViews\Filament\Concerns\InteractsWithSavedViews;
use Modules\SavedViews\Models\SavedView;
use Tests\TestCase;

/**
 * Minimal Livewire host for the trait.
 */
class SavedViewsStub extends Component
{
    use InteractsWithSavedViews;

    public array $activeFilters = [];

    public array $visibleColumns = [];

    public function savedViewResourceKey(): string
    {
        return 'test.stub';
    }

    public function captureViewState(): array
    {
        return ['filters' => $this->activeFilters, 'columns' => $this->visibleColumns];
    }

    public function applyViewState(array $state): void
    {
        $this->activeFilters = $state['filters'] ?? [];
        $this->visibleColumns = $state['columns'] ?? [];
    }

    public function render(): string
    {
        return '<div>{{ $savedViewId }}</div>';
    }
}

class SavedViewsTest extends TestCase
{
    use RefreshDatabase;

    public function test_views_are_scoped_to_their_owner_and_resource(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();

        SavedView::factory()->for($alice)->create(['name' => 'Alice prices', 'resource' => 'pricing.prices']);
        SavedView::factory()->for($alice)->create(['name' => 'Alice products', 'resource' => 'products']);
        SavedView::factory()->for($bob)->create(['name' => 'Bob prices', 'resource' => 'pricing.prices']);

        $this->assertEqualsCanonicalizing(
            ['Alice prices'],
            SavedView::query()->forUser($alice->id)->forResource('pricing.prices')->pluck('name')->all(),
        );

        // deleting the user removes their views
        $alice->delete();
        $this->assertSame(0, SavedView::query()->where('user_id', $alice->id)->count());
        $this->assertSame(1, SavedView::query()->count());
    }

    public function test_name_is_unique_per_user_and_resource(): void
    {
        $user = User::factory()->create();
        SavedView::factory()->for($user)->create(['name' => 'Main', 'resource' => 'pricing.prices']);

        // same name, different resource is fine
        SavedView::factory()->for($user)->create(['name' => 'Main', 'resource' => 'products']);

        $this->expectException(\Illuminate\Database\QueryException::class);
        SavedView::factory()->for($user)->create(['name' => 'Main', 'resource' => 'pricing.prices']);
    }

    public function test_filters_and_columns_round_trip_as_arrays(): void
    {
        $view = SavedView::factory()->create([
            'filters' => ['hasPrice' => 'no', 'search' => 'abc'],
            'columns' => ['name', 'sku'],
        ]);

        $view->refresh();
        $this->assertSame(['hasPrice' => 'no', 'search' => 'abc'], $view->filters);
        $this->assertSame(['name', 'sku'], $view->columns);
    }

    public function test_trait_lists_only_the_current_users_views_and_applies_them(): void
    {
        $alice = User::factory()->create();
        $this->actingAs($alice);

        SavedView::factory()->for($alice)->create([
            'name' => 'No price', 'resource' => 'test.stub',
            'filters' => ['hasPrice' => 'no'], 'columns' => ['sku'],
        ]);
        SavedView::factory()->for(User::factory()->create())->create([
            'name' => 'Someone else', 'resource' => 'test.stub',
        ]);
        $mine = SavedView::query()->where('name', 'No price')->sole();

        Livewire::test(SavedViewsStub::class)
            ->assertSet('savedViewId', null)
            ->tap(fn ($c) => $this->assertSame(
                ['No price'],
                array_values($c->instance()->savedViewOptions()),
            ))
            ->set('savedViewId', $mine->id)
            ->assertSet('activeFilters', ['hasPrice' => 'no'])
            ->assertSet('visibleColumns', ['sku']);
    }
}
