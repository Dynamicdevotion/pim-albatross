<?php

namespace Modules\Wiki\Providers;

use Nwidart\Modules\Support\ModuleServiceProvider;

class WikiServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Wiki';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'wiki';

    /**
     * Provider classes to register.
     *
     * @var string[]
     */
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];
}
