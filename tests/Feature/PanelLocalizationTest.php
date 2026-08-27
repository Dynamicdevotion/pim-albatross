<?php

namespace Tests\Feature;

use App\Models\User;
use BezhanSalleh\LanguageSwitch\LanguageSwitch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Tests\TestCase;

class PanelLocalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_pim_strings_resolve_in_english(): void
    {
        App::setLocale('en');

        $this->assertSame('SKU', __('pim.field.sku'));
        $this->assertSame('Products', __('pim.resource.product.plural'));
        $this->assertSame('Draft', __('pim.option.status.draft'));
    }

    public function test_pim_strings_resolve_in_italian_including_placeholders(): void
    {
        App::setLocale('it');

        $this->assertSame('Prodotti', __('pim.resource.product.plural'));
        $this->assertSame('Bozza', __('pim.option.status.draft'));
        $this->assertSame(
            'Termini assegnati a 3 prodotti',
            __('pim.notification.terms_assigned', ['count' => 3]),
        );
    }

    public function test_native_filament_strings_follow_the_locale(): void
    {
        App::setLocale('en');
        $this->assertSame('Sign out', __('filament-panels::layout.actions.logout.label'));

        App::setLocale('it');
        $this->assertSame('Disconnetti', __('filament-panels::layout.actions.logout.label'));
    }

    public function test_language_switch_reads_the_per_user_locale(): void
    {
        $this->actingAs(User::factory()->create(['locale' => 'it']));
        $this->assertSame('it', LanguageSwitch::make()->getPreferredLocale());

        $this->actingAs(User::factory()->create(['locale' => 'en']));
        $this->assertSame('en', LanguageSwitch::make()->getPreferredLocale());
    }

    public function test_switching_locale_persists_to_the_user(): void
    {
        $user = User::factory()->create(['locale' => 'it']);
        $this->actingAs($user);

        LanguageSwitch::switchLocale('en');
        $this->assertSame('en', $user->fresh()->locale);

        LanguageSwitch::switchLocale('it');
        $this->assertSame('it', $user->fresh()->locale);
    }

    public function test_products_page_renders_localized_labels(): void
    {
        $this->actingAs(User::factory()->create(['locale' => 'it']));
        $this->get('/admin/products')
            ->assertOk()
            ->assertSee('lang="it"', escape: false)
            ->assertSee('Prodotti');

        $this->actingAs(User::factory()->create(['locale' => 'en']));
        $this->get('/admin/products')
            ->assertOk()
            ->assertSee('lang="en"', escape: false)
            ->assertSee('Products');
    }
}
