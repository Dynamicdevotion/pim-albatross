<?php

namespace Modules\Branding\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Modules\Branding\Models\Setting;

/**
 * Wires the panel chrome to the {@see Setting} singleton: the brand name (text
 * fallback when no logo), the brand logo, and the primary theme colour. All
 * three are closures, re-evaluated on every request from a cached snapshot, so
 * a change on the settings page takes effect immediately without clearing any
 * Filament cache.
 */
class BrandingPanelPlugin implements Plugin
{
    public function getId(): string
    {
        return 'branding';
    }

    public function register(Panel $panel): void
    {
        $panel
            ->brandName(fn (): string => Setting::branding()['brand_name'] ?: config('app.name'))
            ->brandLogo(fn (): ?string => Setting::branding()['logo_url'])
            ->brandLogoHeight('2rem')
            ->colors(fn (): array => ['primary' => Setting::primaryPalette()])
            ->discoverPages(
                in: module_path('Branding', 'app/Filament/Pages'),
                for: 'Modules\\Branding\\Filament\\Pages',
            );
    }

    public function boot(Panel $panel): void
    {
        //
    }

    public static function make(): static
    {
        return app(static::class);
    }
}
