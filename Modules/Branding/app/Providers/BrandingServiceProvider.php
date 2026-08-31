<?php

namespace Modules\Branding\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Nwidart\Modules\Support\ModuleServiceProvider;

class BrandingServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Branding';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'branding';

    /**
     * Command classes to register.
     *
     * @var string[]
     */
    // protected array $commands = [];

    /**
     * Provider classes to register.
     *
     * @var string[]
     */
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    /**
     * Define module schedules.
     *
     * @param  $schedule
     */
    // protected function configureSchedules(Schedule $schedule): void
    // {
    //     $schedule->command('inspire')->hourly();
    // }
}
