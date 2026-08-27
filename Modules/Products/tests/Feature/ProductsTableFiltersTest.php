<?php

namespace Modules\Products\Tests\Feature;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Localization\Database\Seeders\LanguageSeeder;
use Modules\Pricing\Models\PriceList;
use Modules\Products\Filament\Resources\Products\Pages\ListProducts;
use Modules\Products\Models\Product;
use Modules\Taxonomies\Models\Taxonomy;
use Modules\Taxonomies\Models\TaxonomyTerm;
use Tests\TestCase;

class ProductsTableFiltersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(LanguageSeeder::class);
        $this->actingAs(User::factory()->create());
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function product(string $sku, string $name = 'P'): Product
    {
        $product = Product::factory()->create(['sku' => $sku]);
        $product->translations()->create(['locale' => 'it', 'name' => $name]);

        return $product;
    }

    public function test_taxonomy_filter_is_and_across_taxonomies_or_within_one(): void
    {
        $cat = Taxonomy::factory()->named('Categoria')->create();
        $scarpe = TaxonomyTerm::factory()->named('Scarpe')->for($cat)->create();

        $colore = Taxonomy::factory()->named('Colore')->create();
        $rosso = TaxonomyTerm::factory()->named('Rosso')->for($colore)->create();
        $blu = TaxonomyTerm::factory()->named('Blu')->for($colore)->create();

        $scarpeRosse = $this->product('SR');
        $scarpeRosse->taxonomyTerms()->attach([$scarpe->id, $rosso->id]);
        $scarpeBlu = $this->product('SB');
        $scarpeBlu->taxonomyTerms()->attach([$scarpe->id, $blu->id]);
        $soloRossa = $this->product('MR');
        $soloRossa->taxonomyTerms()->attach([$rosso->id]);
        $nulla = $this->product('NN');

        // AND across taxonomies: Scarpe + Rosso -> only the shoe that is red
        Livewire::test(ListProducts::class)
            ->filterTable('taxonomy_terms', ['terms' => [$scarpe->id, $rosso->id]])
            ->assertCanSeeTableRecords([$scarpeRosse])
            ->assertCanNotSeeTableRecords([$scarpeBlu, $soloRossa, $nulla]);

        // OR within a taxonomy: Rosso + Blu (both Colore) -> red or blue
        Livewire::test(ListProducts::class)
            ->filterTable('taxonomy_terms', ['terms' => [$rosso->id, $blu->id]])
            ->assertCanSeeTableRecords([$scarpeRosse, $scarpeBlu, $soloRossa])
            ->assertCanNotSeeTableRecords([$nulla]);
    }

    public function test_taxonomy_filter_expands_to_the_selected_terms_subtree(): void
    {
        $cat = Taxonomy::factory()->named('Categoria')->create();
        $abbigliamento = TaxonomyTerm::factory()->named('Abbigliamento')->for($cat)->create();
        $magliette = TaxonomyTerm::factory()->named('Magliette')->childOf($abbigliamento)->create();

        $tagged = $this->product('MAG');
        $tagged->taxonomyTerms()->attach($magliette->id);
        $other = $this->product('OTH');

        Livewire::test(ListProducts::class)
            ->filterTable('taxonomy_terms', ['terms' => [$abbigliamento->id]])
            ->assertCanSeeTableRecords([$tagged])
            ->assertCanNotSeeTableRecords([$other]);
    }

    public function test_price_filter_by_presence_and_range_on_a_list(): void
    {
        $std = PriceList::create(['name' => 'Standard', 'is_default' => true]);
        $other = PriceList::create(['name' => 'Wholesale']);

        $a = $this->product('A');
        $a->prices()->create(['price_list_id' => $std->id, 'price' => 10]);
        $b = $this->product('B');
        $b->prices()->create(['price_list_id' => $std->id, 'price' => 100]);
        $c = $this->product('C'); // no price at all
        $d = $this->product('D');
        $d->prices()->create(['price_list_id' => $other->id, 'price' => 50]); // only on the other list

        Livewire::test(ListProducts::class)
            ->filterTable('price', ['price_list_id' => $std->id, 'presence' => 'with'])
            ->assertCanSeeTableRecords([$a, $b])
            ->assertCanNotSeeTableRecords([$c, $d]);

        Livewire::test(ListProducts::class)
            ->filterTable('price', ['price_list_id' => $std->id, 'presence' => 'without'])
            ->assertCanSeeTableRecords([$c, $d])
            ->assertCanNotSeeTableRecords([$a, $b]);

        Livewire::test(ListProducts::class)
            ->filterTable('price', ['price_list_id' => $std->id, 'min' => 20, 'max' => 200])
            ->assertCanSeeTableRecords([$b])
            ->assertCanNotSeeTableRecords([$a, $c, $d]);
    }

    public function test_stock_filter_zero_and_low_exclude_variable_containers(): void
    {
        $zero = $this->product('Z');
        $zero->update(['stock' => 0]);
        $low = $this->product('L');
        $low->update(['stock' => 3]);
        $plenty = $this->product('K');
        $plenty->update(['stock' => 50]);

        $variable = Product::factory()->variable()->create(['sku' => 'VAR']);
        $variable->translations()->create(['locale' => 'it', 'name' => 'Var']);

        Livewire::test(ListProducts::class)
            ->filterTable('stock', ['level' => 'zero'])
            ->assertCanSeeTableRecords([$zero])
            ->assertCanNotSeeTableRecords([$low, $plenty, $variable]);

        Livewire::test(ListProducts::class)
            ->filterTable('stock', ['level' => 'low'])
            ->assertCanSeeTableRecords([$low])
            ->assertCanNotSeeTableRecords([$zero, $plenty, $variable]);
    }
}
