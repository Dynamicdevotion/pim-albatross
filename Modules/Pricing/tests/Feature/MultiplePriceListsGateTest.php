<?php

namespace Modules\Pricing\Tests\Feature;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Pennant\Feature;
use Livewire\Livewire;
use Modules\Localization\Database\Seeders\LanguageSeeder;
use Modules\Pricing\Filament\Pages\ManagePrices;
use Modules\Pricing\Filament\Resources\PriceLists\Pages\ListPriceLists;
use Modules\Pricing\Filament\Resources\PriceLists\PriceListResource;
use Modules\Pricing\Models\PriceList;
use Modules\Pricing\Support\ProductPriceMatrix;
use Modules\Pricing\Tests\Unit\MultiplePriceListsFeatureTest;
use Tests\TestCase;

/**
 * The commercial gate itself is exercised in {@see MultiplePriceListsFeatureTest}.
 * This covers what the flag actually controls: ProductPriceMatrix::activeLists()
 * (the seam the product/variant price table and the bulk price grid both
 * read through), the Price Lists resource blocking creation of anything
 * beyond the default, and the bulk price grid hiding its list selector.
 *
 * Pennant's default `array` store caches a feature's resolved value per
 * scope for the lifetime of the request (see config/pennant.php) — a test
 * that flips config(...) mid-method must call Feature::flushCache() first,
 * or the second read just returns the first resolution.
 */
class MultiplePriceListsGateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(LanguageSeeder::class);
        $this->actingAs(User::factory()->create());
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_active_lists_collapses_to_the_default_list_when_disabled(): void
    {
        PriceList::create(['name' => 'Standard', 'is_default' => true]);
        PriceList::create(['name' => 'Wholesale']);

        config(['pricing.multiple_price_lists_enabled' => false]);

        $this->assertSame(['Standard'], ProductPriceMatrix::activeLists()->pluck('name')->all());
    }

    public function test_active_lists_returns_every_active_list_when_enabled(): void
    {
        PriceList::create(['name' => 'Standard', 'is_default' => true]);
        PriceList::create(['name' => 'Wholesale']);

        config(['pricing.multiple_price_lists_enabled' => true]);

        $this->assertSame(['Standard', 'Wholesale'], ProductPriceMatrix::activeLists()->pluck('name')->all());
    }

    public function test_disabling_the_flag_does_not_touch_existing_non_default_lists(): void
    {
        PriceList::create(['name' => 'Standard', 'is_default' => true]);
        $wholesale = PriceList::create(['name' => 'Wholesale']);

        config(['pricing.multiple_price_lists_enabled' => false]);
        $this->assertSame(['Standard'], ProductPriceMatrix::activeLists()->pluck('name')->all());

        $this->assertTrue($wholesale->fresh()->active);

        config(['pricing.multiple_price_lists_enabled' => true]);
        Feature::flushCache();
        $this->assertSame(['Standard', 'Wholesale'], ProductPriceMatrix::activeLists()->pluck('name')->all());
    }

    public function test_creating_a_price_list_is_blocked_when_the_flag_is_off(): void
    {
        PriceList::create(['name' => 'Standard', 'is_default' => true]);
        config(['pricing.multiple_price_lists_enabled' => false]);

        $this->assertFalse(PriceListResource::canCreate());
        $this->get(PriceListResource::getUrl('create'))->assertForbidden();
    }

    public function test_creating_a_price_list_is_allowed_when_the_flag_is_on(): void
    {
        PriceList::create(['name' => 'Standard', 'is_default' => true]);
        config(['pricing.multiple_price_lists_enabled' => true]);

        $this->assertTrue(PriceListResource::canCreate());
        $this->get(PriceListResource::getUrl('create'))->assertSuccessful();
    }

    public function test_activating_a_non_default_list_is_rejected_server_side_when_the_flag_is_off(): void
    {
        PriceList::create(['name' => 'Standard', 'is_default' => true]);
        $wholesale = PriceList::create(['name' => 'Wholesale', 'active' => false]);
        config(['pricing.multiple_price_lists_enabled' => false]);

        Livewire::test(ListPriceLists::class)
            ->call('updateTableColumnState', 'active', (string) $wholesale->getKey(), true);

        $this->assertFalse($wholesale->fresh()->active);
    }

    public function test_the_pro_notice_shows_only_when_the_flag_is_off(): void
    {
        PriceList::create(['name' => 'Standard', 'is_default' => true]);

        config(['pricing.multiple_price_lists_enabled' => false]);
        Livewire::test(ListPriceLists::class)->assertSee(__('pim.pricing.pro_notice.heading'));

        config(['pricing.multiple_price_lists_enabled' => true]);
        Feature::flushCache();
        Livewire::test(ListPriceLists::class)->assertDontSee(__('pim.pricing.pro_notice.heading'));
    }

    public function test_the_bulk_price_grid_hides_its_list_selector_when_disabled(): void
    {
        PriceList::create(['name' => 'Standard', 'is_default' => true]);
        PriceList::create(['name' => 'Wholesale']);
        config(['pricing.multiple_price_lists_enabled' => false]);

        Livewire::test(ManagePrices::class)
            ->assertDontSeeHtml('wire:model.live="priceListId"')
            ->assertSee('Standard');
    }

    public function test_the_bulk_price_grid_shows_its_list_selector_when_enabled(): void
    {
        PriceList::create(['name' => 'Standard', 'is_default' => true]);
        PriceList::create(['name' => 'Wholesale']);
        config(['pricing.multiple_price_lists_enabled' => true]);

        Livewire::test(ManagePrices::class)
            ->assertSeeHtml('wire:model.live="priceListId"')
            ->assertSee('Wholesale');
    }
}
