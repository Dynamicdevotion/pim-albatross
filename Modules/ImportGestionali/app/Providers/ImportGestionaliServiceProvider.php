<?php

namespace Modules\ImportGestionali\Providers;

use Modules\ImportGestionali\Console\PruneImportFilesCommand;
use Nwidart\Modules\Support\ModuleServiceProvider;

class ImportGestionaliServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'ImportGestionali';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'importgestionali';

    /**
     * Command classes to register.
     *
     * @var string[]
     */
    protected array $commands = [
        PruneImportFilesCommand::class,
    ];

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
