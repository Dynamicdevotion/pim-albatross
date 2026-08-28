<?php

namespace Modules\ImportGestionali\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\ImportGestionali\Models\ImportRecord;
use Modules\ImportGestionali\Support\ImportRunner;
use Throwable;

/**
 * Runs a large import off the request. Needs the scheduled
 * `queue:work --stop-when-empty` (see routes/console.php) to be picked up.
 */
class RunProductImport implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 1800;

    public int $tries = 1;

    public function __construct(public ImportRecord $record) {}

    public function handle(ImportRunner $runner): void
    {
        $runner->run($this->record);
    }

    public function failed(Throwable $exception): void
    {
        $this->record->update([
            'status' => 'failed',
            'error_message' => __('pim.import.error.unexpected'),
            'finished_at' => now(),
        ]);
    }
}
