<?php

namespace Modules\Dashboard\Tests\Feature;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Modules\Dashboard\Filament\Widgets\ProductOverviewStats;
use Modules\Dashboard\Filament\Widgets\ProductsByCategoryChart;
use Modules\Dashboard\Filament\Widgets\ProductsMissingImage;
use Modules\Dashboard\Filament\Widgets\RecentImportIssues;
use Modules\ImportGestionali\Models\ImportRecord;
use Modules\Localization\Database\Seeders\LanguageSeeder;
use Modules\Localization\Support\Locales;
use Modules\Pricing\Models\PriceList;
use Modules\Products\Filament\Resources\Products\ProductResource;
use Modules\Products\Models\Product;
use Modules\Taxonomies\Models\Taxonomy;
use Tests\TestCase;

class DashboardWidgetsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(LanguageSeeder::class);
        $this->actingAs(User::factory()->create());
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function product(string $sku, string $name = 'P', array $overrides = []): Product
    {
        $product = Product::factory()->create(array_merge(['sku' => $sku], $overrides));
        $product->translations()->create(['locale' => 'it', 'name' => $name]);

        return $product;
    }

    /**
     * Invoke a protected method on a widget instance.
     */
    private function invoke(object $widget, string $method): mixed
    {
        return \Closure::bind(fn () => $this->{$method}(), $widget, $widget::class)();
    }

    public function test_overview_stats_count_and_link_with_the_same_filter(): void
    {
        PriceList::create(['name' => 'Standard', 'is_default' => true]);

        $this->product('A1', 'A1', ['status' => 'active', 'stock' => 5]);
        $this->product('A2', 'A2', ['status' => 'active', 'stock' => 5]);
        $this->product('D1', 'D1', ['status' => 'draft', 'stock' => 5]);
        $this->product('Z1', 'Z1', ['status' => 'active', 'stock' => 0]);

        $stats = $this->invoke(new ProductOverviewStats, 'getStats');
        $byLabel = collect($stats)->keyBy(fn ($stat) => $stat->getLabel());

        $active = $byLabel[__('pim.dashboard.stat.active')];
        $this->assertSame('3', (string) $active->getValue());
        $this->assertSame(
            ProductResource::getUrl('index', ['filters' => ['status' => ['value' => 'active']]]),
            $active->getUrl(),
        );

        $stockZero = $byLabel[__('pim.dashboard.stat.stock_zero')];
        $this->assertSame('1', (string) $stockZero->getValue());
        $this->assertSame(
            ProductResource::getUrl('index', ['filters' => ['stock' => ['level' => 'zero']]]),
            $stockZero->getUrl(),
        );

        // no-price stat is present because a default list exists
        $this->assertTrue($byLabel->has(__('pim.dashboard.stat.no_price')));
    }

    public function test_no_price_stat_is_hidden_without_a_default_price_list(): void
    {
        $this->product('P1');

        $stats = $this->invoke(new ProductOverviewStats, 'getStats');
        $labels = collect($stats)->map(fn ($stat) => $stat->getLabel());

        $this->assertFalse($labels->contains(__('pim.dashboard.stat.no_price')));
    }

    public function test_missing_translation_stat_matches_the_any_language_clause(): void
    {
        $complete = Product::factory()->create(['sku' => 'FULL']);
        foreach (Locales::activeCodes() as $code) {
            $complete->translations()->create(['locale' => $code, 'name' => "F {$code}"]);
        }
        $this->product('PARTIAL', 'Solo it');

        $stat = collect($this->invoke(new ProductOverviewStats, 'getStats'))
            ->first(fn ($s) => $s->getLabel() === __('pim.dashboard.stat.missing_translation'));

        $this->assertSame('1', (string) $stat->getValue());
        $this->assertSame(
            ProductResource::getUrl('index', ['filters' => ['missing_translation' => ['value' => '*']]]),
            $stat->getUrl(),
        );
    }

    public function test_products_missing_image_lists_only_top_level_products_without_a_main_image(): void
    {
        Storage::fake('public');

        $withImage = Product::factory()->withMainImage()->create(['sku' => 'WITH']);
        $withImage->translations()->create(['locale' => 'it', 'name' => 'With']);

        $without = $this->product('WITHOUT');

        $variableParent = Product::factory()->variable()->create(['sku' => 'VAR']);
        $variableParent->translations()->create(['locale' => 'it', 'name' => 'Var']);
        Product::factory()->variantOf($variableParent)->create(['sku' => 'VAR-1']);

        Livewire::test(ProductsMissingImage::class)
            ->assertCanSeeTableRecords([$without, $variableParent])
            ->assertCanNotSeeTableRecords([$withImage]);
    }

    public function test_category_chart_counts_products_per_term_and_carries_a_link(): void
    {
        $taxonomy = Taxonomy::create(['slug' => 'categoria']);
        $taxonomy->translations()->create(['locale' => 'it', 'name' => 'Categoria']);

        $abbigliamento = $taxonomy->terms()->create(['slug' => 'abbigliamento']);
        $abbigliamento->translations()->create(['locale' => 'it', 'name' => 'Abbigliamento']);
        $calzature = $taxonomy->terms()->create(['slug' => 'calzature']);
        $calzature->translations()->create(['locale' => 'it', 'name' => 'Calzature']);

        $this->product('P1')->taxonomyTerms()->attach($abbigliamento->id);
        $this->product('P2')->taxonomyTerms()->attach($abbigliamento->id);
        $this->product('P3')->taxonomyTerms()->attach($calzature->id);

        $data = $this->invoke(new ProductsByCategoryChart, 'getData');

        $this->assertSame(['Abbigliamento', 'Calzature'], $data['labels']);
        $this->assertSame([2, 1], $data['datasets'][0]['data']);
        $this->assertSame(
            ProductResource::getUrl('index', ['filters' => ['taxonomy_terms' => ['terms' => [$abbigliamento->id]]]]),
            $data['datasets'][0]['urls'][0],
        );
    }

    public function test_category_chart_is_empty_without_the_categoria_taxonomy(): void
    {
        $data = $this->invoke(new ProductsByCategoryChart, 'getData');

        $this->assertSame([], $data['labels']);
        $this->assertSame([], $data['datasets'][0]['data']);
    }

    public function test_recent_import_issues_shows_the_latest_run_with_skips_and_links_to_it(): void
    {
        ImportRecord::factory()->create([
            'status' => 'completed',
            'skipped_count' => 0,
            'created_at' => now()->subDay(),
        ]);

        $latest = ImportRecord::factory()->create([
            'status' => 'completed',
            'original_filename' => 'gestionale.csv',
            'skipped_count' => 2,
            'issues' => [
                ['line' => 4, 'reason' => 'riga 4: SKU mancante'],
                ['line' => 9, 'reason' => 'riga 9: prezzo non numerico («abc»)'],
            ],
        ]);

        Livewire::test(RecentImportIssues::class)
            ->assertOk()
            ->assertSee('riga 4: SKU mancante')
            ->assertSee('riga 9: prezzo non numerico')
            ->assertSee('gestionale.csv');
    }

    public function test_recent_import_issues_empty_state(): void
    {
        Livewire::test(RecentImportIssues::class)
            ->assertOk()
            ->assertSee(__('pim.dashboard.import_issues.empty'));
    }
}
