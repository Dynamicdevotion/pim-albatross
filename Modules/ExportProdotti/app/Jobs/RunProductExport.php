<?php

namespace Modules\ExportProdotti\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\ExportProdotti\Models\ExportRecord;
use Modules\ExportProdotti\Support\ExportRunner;
use Throwable;

/**
 * Generates a large export off the request. Needs the scheduled
 * `queue:work --stop-when-empty` (see routes/console.php) to be picked up —
 * without the Netsons cron the run stays `pending`, exactly like a large
 * ImportGestionali import.
 */
class RunProductExport implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 1800;

    public int $tries = 1;

    public function __construct(public ExportRecord $record) {}

    public function handle(ExportRunner $runner): void
    {
        $runner->run($this->record);
    }

    public function failed(Throwable $exception): void
    {
        $this->record->update([
            'status' => 'failed',
            'error_message' => __('pim.export.error.unexpected'),
            'finished_at' => now(),
        ]);
    }
}
