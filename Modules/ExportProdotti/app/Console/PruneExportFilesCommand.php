<?php

namespace Modules\ExportProdotti\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Modules\ExportProdotti\Models\ExportRecord;

/**
 * Deletes the generated file of exports older than the retention window. The
 * {@see ExportRecord} rows (the reports) are kept — mirrors
 * `importgestionali:prune-files`.
 */
class PruneExportFilesCommand extends Command
{
    protected $signature = 'exportprodotti:prune-files {--days= : Override the retention window}';

    protected $description = 'Delete generated product-export files older than the retention window';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?: config('exportprodotti.prune_days', 7));
        $disk = Storage::disk(config('exportprodotti.disk'));
        $cutoff = now()->subDays($days);

        $records = ExportRecord::query()
            ->whereNotNull('stored_path')
            ->where('created_at', '<', $cutoff)
            ->get();

        $removed = 0;

        foreach ($records as $record) {
            if ($disk->exists($record->stored_path)) {
                $disk->delete($record->stored_path);
            }

            $record->update(['stored_path' => null]);
            $removed++;
        }

        $this->info("Pruned {$removed} export file(s) older than {$days} day(s).");

        return self::SUCCESS;
    }
}
