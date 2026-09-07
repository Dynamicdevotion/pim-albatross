<?php

namespace Modules\Pricing\Providers;

use Modules\Pricing\Support\MultiplePriceListsFeature;
use Nwidart\Modules\Support\ModuleServiceProvider;

class PricingServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Pricing';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'pricing';

    /**
     * Provider classes to register.
     *
     * @var string[]
     */
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    public function boot(): void
    {
        parent::boot();

        MultiplePriceListsFeature::defineFeature();
    }
}
