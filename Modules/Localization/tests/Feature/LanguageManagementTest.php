<?php

namespace Modules\Localization\Tests\Feature;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Localization\Database\Seeders\LanguageSeeder;
use Modules\Localization\Filament\Resources\Languages\Pages\ListLanguages;
use Modules\Localization\Models\Language;
use Modules\Localization\Support\Locales;
use Tests\TestCase;

class LanguageManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_migration_ships_the_catalogue_and_the_seeder_is_idempotent(): void
    {
        // create_languages_table already inserted the catalogue
        $this->assertGreaterThanOrEqual(20, Language::count());
        $this->assertEqualsCanonicalizing(
            ['it', 'en', 'es', 'fr', 'de'],
            Language::query()->where('active', true)->pluck('code')->all(),
        );
        $this->assertSame('it', Language::query()->where('is_base', true)->sole()->code);

        $before = Language::count();
        (new LanguageSeeder())->run();
        $this->assertSame($before, Language::count());
    }

    public function test_locales_facade(): void
    {
        $this->assertSame('it', Locales::baseCode());
        $this->assertSame('Italiano', Locales::base()->name);
        $this->assertEqualsCanonicalizing(['it', 'en', 'es', 'fr', 'de'], Locales::activeCodes());
        $this->assertSame('it', Locales::active()->first()->code); // base sorts first

        $enId = Locales::idFor('en');
        $this->assertIsInt($enId);
        $this->assertSame('en', Locales::codeFor($enId));
        $this->assertNull(Locales::idFor('xx'));
    }

    public function test_language_list_renders_and_finds_a_language(): void
    {
        $italian = Language::query()->where('code', 'it')->sole();

        Livewire::test(ListLanguages::class)
            ->assertSuccessful()
            ->searchTable('Italiano')
            ->assertCanSeeTableRecords([$italian]);
    }

    public function test_language_content_helper_is_safe_before_language_id_columns_exist(): void
    {
        // product_translations still keys by `locale` at this step, so there is
        // no language_id column to scan yet.
        $this->assertFalse(\Modules\Localization\Support\LanguageContent::has(Locales::base()));
        $this->assertSame(0, \Modules\Localization\Support\LanguageContent::purge(Locales::base()));
    }
}
