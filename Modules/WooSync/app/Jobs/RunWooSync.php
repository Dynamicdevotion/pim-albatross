<?php

namespace Modules\WooSync\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\WooSync\Models\WooSyncRun;
use Modules\WooSync\Support\WooSyncRunner;
use Throwable;

/**
 * Runs a large bulk sync off the request. Needs the scheduled
 * `queue:work --stop-when-empty` (Netsons has no persistent worker) to be
 * picked up — without the cron the run stays `pending`, exactly like a large
 * ImportGestionali import or ExportProdotti export.
 */
class RunWooSync implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 1800;

    public int $tries = 1;

    public function __construct(public WooSyncRun $run) {}

    public function handle(WooSyncRunner $runner): void
    {
        $runner->run($this->run);
    }

    public function failed(?Throwable $exception): void
    {
        $this->run->update([
            'status' => 'failed',
            'error_message' => __('pim.woosync.error.unexpected'),
            'finished_at' => now(),
        ]);
    }
}
