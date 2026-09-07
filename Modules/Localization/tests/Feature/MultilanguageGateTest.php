<?php

namespace Modules\Localization\Tests\Feature;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Pennant\Feature;
use Livewire\Livewire;
use Modules\Localization\Filament\Resources\Languages\LanguageResource;
use Modules\Localization\Filament\Resources\Languages\Pages\ListLanguages;
use Modules\Localization\Models\Language;
use Modules\Localization\Support\Locales;
use Modules\Localization\Tests\Unit\MultilanguageFeatureTest;
use Tests\TestCase;

/**
 * The commercial gate itself is exercised in {@see MultilanguageFeatureTest}.
 * This covers what the flag actually controls: Locales::active() (the single
 * seam every translation tab/filter/export reads through) and the Languages
 * resource blocking creation of anything beyond the base language.
 *
 * Pennant's default `array` store caches a feature's resolved value per
 * scope for the lifetime of the request (see config/pennant.php) — a test
 * that flips config(...) mid-method must call Feature::flushCache() first,
 * or the second read just returns the first resolution.
 */
class MultilanguageGateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_locales_active_collapses_to_the_base_language_when_disabled(): void
    {
        config(['localization.multilanguage_enabled' => false]);

        $this->assertEqualsCanonicalizing(['it'], Locales::activeCodes());
        $this->assertSame('it', Locales::active()->sole()->code);
    }

    public function test_locales_active_returns_every_active_language_when_enabled(): void
    {
        config(['localization.multilanguage_enabled' => true]);

        $this->assertEqualsCanonicalizing(['it', 'en', 'es', 'fr', 'de'], Locales::activeCodes());
    }

    public function test_disabling_the_flag_does_not_touch_existing_non_base_languages(): void
    {
        config(['localization.multilanguage_enabled' => false]);

        $this->assertTrue(Language::query()->where('code', 'en')->value('active'));
        $this->assertEqualsCanonicalizing(['it'], Locales::activeCodes());

        config(['localization.multilanguage_enabled' => true]);
        Feature::flushCache();

        $this->assertEqualsCanonicalizing(['it', 'en', 'es', 'fr', 'de'], Locales::activeCodes());
    }

    public function test_creating_a_language_is_blocked_when_the_flag_is_off(): void
    {
        config(['localization.multilanguage_enabled' => false]);

        $this->assertFalse(LanguageResource::canCreate());
        $this->get(LanguageResource::getUrl('create'))->assertForbidden();
    }

    public function test_creating_a_language_is_allowed_when_the_flag_is_on(): void
    {
        config(['localization.multilanguage_enabled' => true]);

        $this->assertTrue(LanguageResource::canCreate());
        $this->get(LanguageResource::getUrl('create'))->assertSuccessful();
    }

    public function test_activating_a_non_base_language_is_rejected_server_side_when_the_flag_is_off(): void
    {
        config(['localization.multilanguage_enabled' => false]);
        $english = Language::query()->where('code', 'en')->sole();
        $english->update(['active' => false]);

        Livewire::test(ListLanguages::class)
            ->call('updateTableColumnState', 'active', (string) $english->getKey(), true);

        $this->assertFalse($english->fresh()->active);
    }

    public function test_the_pro_notice_shows_only_when_the_flag_is_off(): void
    {
        config(['localization.multilanguage_enabled' => false]);
        Livewire::test(ListLanguages::class)->assertSee(__('pim.localization.pro_notice.heading'));

        config(['localization.multilanguage_enabled' => true]);
        Feature::flushCache();
        Livewire::test(ListLanguages::class)->assertDontSee(__('pim.localization.pro_notice.heading'));
    }
}
