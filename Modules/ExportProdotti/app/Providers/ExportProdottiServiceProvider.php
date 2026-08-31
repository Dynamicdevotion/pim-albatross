<?php

namespace Modules\ExportProdotti\Providers;

use Modules\ExportProdotti\Console\PruneExportFilesCommand;
use Nwidart\Modules\Support\ModuleServiceProvider;

class ExportProdottiServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'ExportProdotti';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'exportprodotti';

    /**
     * Command classes to register.
     *
     * @var string[]
     */
    protected array $commands = [
        PruneExportFilesCommand::class,
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
