<?php

namespace Modules\Branding\Tests\Feature;

use App\Models\User;
use Filament\Facades\Filament;
use Filament\Support\Colors\Color;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Modules\Branding\Filament\Pages\ManageBranding;
use Modules\Branding\Models\Setting;
use Tests\TestCase;

class BrandingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Storage::fake('public');
    }

    public function test_current_is_a_singleton(): void
    {
        $a = Setting::current();
        $b = Setting::current();

        $this->assertTrue($a->is($b));
        $this->assertSame(1, Setting::query()->count());
    }

    public function test_branding_snapshot_defaults_to_empty_and_amber(): void
    {
        $this->assertSame(
            ['brand_name' => null, 'primary_color' => null, 'logo_url' => null],
            Setting::branding(),
        );
        $this->assertSame(Color::Amber, Setting::primaryPalette());
    }

    public function test_saving_the_page_stores_name_and_palette_and_flushes_the_cache(): void
    {
        // prime the cache
        Setting::branding();

        Livewire::test(ManageBranding::class)
            ->fillForm([
                'brand_name' => 'Albatross',
                'primary_color' => 'blue',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $setting = Setting::current();
        $this->assertSame('Albatross', $setting->brand_name);
        $this->assertSame('blue', $setting->primary_color);

        $this->assertSame('Albatross', Setting::branding()['brand_name']);
        $this->assertSame(Color::all()['blue'], Setting::primaryPalette());
    }

    public function test_primary_palette_options_are_named_filament_palettes_with_a_swatch(): void
    {
        $options = Setting::primaryPaletteOptions();

        $this->assertSame(Setting::PRIMARY_PALETTES, array_keys($options));

        foreach ($options as $name => $label) {
            $this->assertArrayHasKey($name, Color::all());
            $this->assertStringContainsString('background:', $label);
            $this->assertStringContainsString(ucfirst($name), $label);
        }
    }

    public function test_uploading_a_logo_populates_the_media_collection_and_snapshot(): void
    {
        Livewire::test(ManageBranding::class)
            ->fillForm([
                'logo' => [UploadedFile::fake()->image('logo.png', 200, 80)],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $setting = Setting::current();
        $this->assertNotNull($setting->getFirstMedia('logo'));
        $this->assertNotNull(Setting::branding()['logo_url']);
        $this->assertSame($setting->getFirstMediaUrl('logo'), Setting::branding()['logo_url']);
    }

    public function test_primary_palette_falls_back_to_amber_for_an_unknown_value(): void
    {
        Setting::factory()->create(['primary_color' => 'not-a-palette']);

        $this->assertSame(Color::Amber, Setting::primaryPalette());
    }

    public function test_panel_brand_name_uses_the_setting_with_a_text_fallback(): void
    {
        $panel = Filament::getPanel('admin');

        $this->assertSame(config('app.name'), $panel->getBrandName());
        $this->assertNull($panel->getBrandLogo());

        Setting::factory()->create(['brand_name' => 'Albatross']);

        $this->assertSame('Albatross', $panel->getBrandName());
    }
}
