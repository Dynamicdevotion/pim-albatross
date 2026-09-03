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

/**
 * A second stub on a different resource key, to prove the remembered view is
 * scoped per-screen and doesn't leak across them.
 */
class SavedViewsOtherStub extends SavedViewsStub
{
    public function savedViewResourceKey(): string
    {
        return 'test.other-stub';
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

    // ---- session persistence across visits ---------------------------------

    public function test_the_active_view_is_remembered_in_session_and_restored_on_the_next_mount(): void
    {
        $alice = User::factory()->create();
        $this->actingAs($alice);

        $view = SavedView::factory()->for($alice)->create([
            'name' => 'No price', 'resource' => 'test.stub',
            'filters' => ['hasPrice' => 'no'], 'columns' => ['sku'],
        ]);

        Livewire::test(SavedViewsStub::class)->set('savedViewId', $view->id);

        // A fresh component instance simulates leaving the page and coming
        // back within the same browser session.
        Livewire::test(SavedViewsStub::class)
            ->assertSet('savedViewId', $view->id)
            ->assertSet('activeFilters', ['hasPrice' => 'no'])
            ->assertSet('visibleColumns', ['sku']);
    }

    public function test_deselecting_the_view_stops_it_from_being_restored(): void
    {
        $alice = User::factory()->create();
        $this->actingAs($alice);

        $view = SavedView::factory()->for($alice)->create(['name' => 'V', 'resource' => 'test.stub']);

        Livewire::test(SavedViewsStub::class)->set('savedViewId', $view->id);
        Livewire::test(SavedViewsStub::class)->set('savedViewId', null);

        Livewire::test(SavedViewsStub::class)->assertSet('savedViewId', null);
    }

    public function test_a_view_deleted_elsewhere_is_not_restored_and_the_remembered_id_is_cleared(): void
    {
        $alice = User::factory()->create();
        $this->actingAs($alice);

        $view = SavedView::factory()->for($alice)->create(['name' => 'V', 'resource' => 'test.stub']);

        Livewire::test(SavedViewsStub::class)->set('savedViewId', $view->id);
        $view->delete();

        Livewire::test(SavedViewsStub::class)->assertSet('savedViewId', null);
    }

    public function test_the_remembered_view_is_scoped_per_screen(): void
    {
        $alice = User::factory()->create();
        $this->actingAs($alice);

        $view = SavedView::factory()->for($alice)->create(['name' => 'V', 'resource' => 'test.stub']);

        Livewire::test(SavedViewsStub::class)->set('savedViewId', $view->id);

        // a different screen (different resource key) must not pick up the
        // view remembered for "test.stub".
        Livewire::test(SavedViewsOtherStub::class)->assertSet('savedViewId', null);
    }
}
