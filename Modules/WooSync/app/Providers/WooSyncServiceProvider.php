<?php

namespace Modules\WooSync\Providers;

use Modules\Products\Support\ProductRowActions;
use Modules\WooSync\Contracts\WooCommerceClient;
use Modules\WooSync\Filament\Actions\SyncProductsAction;
use Modules\WooSync\Models\WooSyncSetting;
use Modules\WooSync\Support\Http\BasicAuthWooClient;
use Modules\WooSync\Support\WooSync;
use Nwidart\Modules\Support\ModuleServiceProvider;

class WooSyncServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'WooSync';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'woosync';

    /**
     * Provider classes to register.
     *
     * @var string[]
     */
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    public function register(): void
    {
        parent::register();

        // The WooCommerce REST client: one concrete implementation today
        // (Basic Auth over HTTPS), bound behind the contract so the runner and
        // the payload builders never see the transport. Resolved with the
        // current connection row so tests can stub settings + `Http::fake()`.
        $this->app->bind(
            WooCommerceClient::class,
            fn (): BasicAuthWooClient => new BasicAuthWooClient(WooSyncSetting::current()),
        );
    }

    public function boot(): void
    {
        parent::boot();

        WooSync::defineFeature();

        static::registerProductActions();
    }

    /**
     * Contribute the "Sincronizza con WooCommerce" single-row and bulk actions
     * to the products list through the Products module's generic registry —
     * only when the commercial flag is on. Products never references WooSync
     * (dependency inversion): it just exposes the hook.
     *
     * Public + static so the feature-flag test can exercise it directly.
     */
    public static function registerProductActions(): void
    {
        if (! WooSync::enabled()) {
            return;
        }

        ProductRowActions::registerRecord(static fn () => SyncProductsAction::record());
        ProductRowActions::registerBulk(static fn () => SyncProductsAction::bulk());
    }
}
