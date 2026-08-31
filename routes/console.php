<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Netsons has no long-running queue worker. A single cPanel cron
 *   * * * * * cd ~/apps/pim && php artisan schedule:run >> /dev/null 2>&1
 * drains the queue once a minute (drains and exits) and prunes old
 * import/export files. Large ImportGestionali and ExportProdotti runs rely
 * on this.
 */
Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=1 --sleep=1')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('importgestionali:prune-files')->dailyAt('03:10');
Schedule::command('exportprodotti:prune-files')->dailyAt('03:20');
