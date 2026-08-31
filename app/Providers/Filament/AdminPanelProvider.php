<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Modules\Branding\Filament\BrandingPanelPlugin;
use Modules\Dashboard\Filament\DashboardPanelPlugin;
use Modules\ExportProdotti\Filament\ExportProdottiPanelPlugin;
use Modules\ImportGestionali\Filament\ImportGestionaliPanelPlugin;
use Modules\Localization\Filament\LocalizationPanelPlugin;
use Modules\Pricing\Filament\PricingPanelPlugin;
use Modules\Products\Filament\ProductsPanelPlugin;
use Modules\Taxonomies\Filament\TaxonomiesPanelPlugin;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('admin')
            ->path('admin')
            ->login()
            // Redundant with the products-list filter drawer; the panel has no
            // other resource worth a global search box.
            ->globalSearch(false)
            // Base fallback; BrandingPanelPlugin appends a dynamic `primary`
            // from the settings row (also falling back to Amber when unset).
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/Admin/Resources'), for: 'App\Filament\Admin\Resources')
            ->discoverPages(in: app_path('Filament/Admin/Pages'), for: 'App\Filament\Admin\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Admin/Widgets'), for: 'App\Filament\Admin\Widgets')
            // Stock widgets replaced by the Dashboard module (see DashboardPanelPlugin).
            ->widgets([])
            ->plugins([
                LocalizationPanelPlugin::make(),
                ProductsPanelPlugin::make(),
                TaxonomiesPanelPlugin::make(),
                PricingPanelPlugin::make(),
                ImportGestionaliPanelPlugin::make(),
                ExportProdottiPanelPlugin::make(),
                BrandingPanelPlugin::make(),
                DashboardPanelPlugin::make(),
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
