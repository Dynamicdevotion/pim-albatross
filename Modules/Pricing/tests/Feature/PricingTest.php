<?php

namespace Modules\Pricing\Tests\Feature;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Localization\Database\Seeders\LanguageSeeder;
use Modules\Pricing\Database\Seeders\PricingSeeder;
use Modules\Pricing\Filament\Pages\ManagePrices;
use Modules\Pricing\Filament\Resources\PriceLists\Pages\CreatePriceList;
use Modules\Pricing\Filament\Resources\PriceLists\Pages\ListPriceLists;
use Modules\Pricing\Models\PriceList;
use Modules\Pricing\Models\ProductPrice;
use Modules\Products\Filament\Resources\Products\Pages\CreateProduct;
use Modules\Products\Models\Product;
use RuntimeException;
use Tests\TestCase;

class PricingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(LanguageSeeder::class);
        $this->actingAs(User::factory()->create());
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function product(string $name, string $sku): Product
    {
        $product = Product::create(['sku' => $sku, 'status' => 'draft']);
        $product->translations()->create(['locale' => 'it', 'name' => $name]);

        return $product;
    }

    public function test_exactly_one_default_price_list(): void
    {
        $a = PriceList::create(['name' => 'A', 'is_default' => true]);
        $b = PriceList::create(['name' => 'B', 'is_default' => true]);

        $this->assertFalse($a->fresh()->is_default);
        $this->assertTrue($b->fresh()->is_default);
        $this->assertSame(1, PriceList::query()->where('is_default', true)->count());

        $this->expectException(RuntimeException::class);
        $b->delete();
    }

    public function test_prices_cascade_on_delete(): void
    {
        $list = PriceList::create(['name' => 'L']);
        $p = $this->product('Sedia', 'C-1');
        $p->prices()->create(['price_list_id' => $list->id, 'price' => 19.90]);

        $this->assertSame('19.90', (string) $p->fresh()->prices->first()->price);

        $list->delete();
        $this->assertDatabaseCount('product_prices', 0);

        $list2 = PriceList::create(['name' => 'L2']);
        $p->prices()->create(['price_list_id' => $list2->id, 'price' => 5]);
        $p->delete();
        $this->assertDatabaseCount('product_prices', 0);
    }

    public function test_seeder_creates_standard_default_and_is_idempotent(): void
    {
        (new PricingSeeder())->run();
        (new PricingSeeder())->run();

        $this->assertSame(1, PriceList::query()->count());
        $standard = PriceList::query()->sole();
        $this->assertSame('Standard', $standard->name);
        $this->assertTrue($standard->is_default);
        $this->assertTrue($standard->active);
    }

    public function test_create_price_list_populates_from_another_with_a_percentage(): void
    {
        $source = PriceList::create(['name' => 'Base']);
        $p1 = $this->product('Uno', 'P-1');
        $p2 = $this->product('Due', 'P-2');
        $source->prices()->create(['product_id' => $p1->id, 'price' => 100]);
        $source->prices()->create(['product_id' => $p2->id, 'price' => 50]);

        Livewire::test(CreatePriceList::class)
            ->fillForm([
                'name' => 'Wholesale',
                'active' => true,
                'source_price_list_id' => $source->id,
                'adjustment_percent' => -15,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $wholesale = PriceList::query()->where('name', 'Wholesale')->sole();
        $this->assertEqualsCanonicalizing(
            ['85.00', '42.50'],
            $wholesale->prices->pluck('price')->map(fn ($p) => (string) $p)->all(),
        );
    }

    public function test_set_default_action_moves_the_flag(): void
    {
        $a = PriceList::create(['name' => 'A', 'is_default' => true]);
        $b = PriceList::create(['name' => 'B', 'active' => false]);

        Livewire::test(ListPriceLists::class)
            ->callTableAction('setDefault', $b);

        $this->assertFalse($a->fresh()->is_default);
        $this->assertTrue($b->fresh()->is_default);
        $this->assertTrue($b->fresh()->active); // forced active
    }

    public function test_manage_prices_inline_write_and_clear(): void
    {
        $list = PriceList::create(['name' => 'Std', 'is_default' => true]);
        $p = $this->product('Tavolo', 'T-1');

        $component = Livewire::test(ManagePrices::class)
            ->set('priceListId', $list->id)
            ->assertCanSeeTableRecords([$p]);

        $component->call('writePrice', $p->id, '29.9');
        $this->assertDatabaseHas('product_prices', [
            'product_id' => $p->id, 'price_list_id' => $list->id, 'price' => 29.90,
        ]);

        $component->call('writePrice', $p->id, '');
        $this->assertDatabaseCount('product_prices', 0);
    }

    public function test_manage_prices_filter_and_bulk_set(): void
    {
        $list = PriceList::create(['name' => 'Std', 'is_default' => true]);
        $withPrice = $this->product('Con', 'W-1');
        $withPrice->prices()->create(['price_list_id' => $list->id, 'price' => 10]);
        $withoutPrice = $this->product('Senza', 'W-2');

        Livewire::test(ManagePrices::class)
            ->set('priceListId', $list->id)
            ->filterTable('has_price', false)
            ->assertCanSeeTableRecords([$withoutPrice])
            ->assertCanNotSeeTableRecords([$withPrice])
            ->removeTableFilter('has_price')
            ->callTableBulkAction('setPrice', [$withPrice, $withoutPrice], ['price' => 7.5]);

        $this->assertDatabaseHas('product_prices', ['product_id' => $withoutPrice->id, 'price' => 7.50]);
        $this->assertDatabaseHas('product_prices', ['product_id' => $withPrice->id, 'price' => 7.50]);
    }

    public function test_product_form_repeater_writes_per_list_prices(): void
    {
        $a = PriceList::create(['name' => 'A', 'is_default' => true]);
        $b = PriceList::create(['name' => 'B']);

        Livewire::test(CreateProduct::class)
            ->fillForm([
                'sku' => 'PR-FORM',
                'status' => 'draft',
                'translations' => ['it' => ['name' => 'Prezzato']],
                'prices' => [
                    ['price_list_id' => $a->id, 'price' => '11.00'],
                    ['price_list_id' => $b->id, 'price' => '9.50'],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $product = Product::query()->where('sku', 'PR-FORM')->sole();
        $this->assertEqualsCanonicalizing(
            [$a->id, $b->id],
            $product->prices->pluck('price_list_id')->all(),
        );
    }
}
