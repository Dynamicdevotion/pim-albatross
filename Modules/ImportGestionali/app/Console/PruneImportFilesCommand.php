<?php

namespace Modules\ImportGestionali\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Modules\ImportGestionali\Models\ImportRecord;

/**
 * Deletes the stored source file of imports older than the retention window.
 * The {@see ImportRecord} rows (the reports) are kept.
 */
class PruneImportFilesCommand extends Command
{
    protected $signature = 'importgestionali:prune-files {--days= : Override the retention window}';

    protected $description = 'Delete stored import source files older than the retention window';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?: config('importgestionali.prune_days', 7));
        $disk = Storage::disk(config('importgestionali.disk'));
        $cutoff = now()->subDays($days);

        $records = ImportRecord::query()
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

        $this->info("Pruned {$removed} import file(s) older than {$days} day(s).");

        return self::SUCCESS;
    }
}
