<?php

namespace Modules\Localization\Providers;

use Modules\Localization\Support\MultilanguageFeature;
use Nwidart\Modules\Support\ModuleServiceProvider;

class LocalizationServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Localization';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'localization';

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

        MultilanguageFeature::defineFeature();
    }
}
