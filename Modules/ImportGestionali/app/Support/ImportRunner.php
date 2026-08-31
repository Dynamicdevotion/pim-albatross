<?php

namespace Modules\ImportGestionali\Support;

use Illuminate\Support\Facades\Storage;
use Modules\ImportGestionali\Models\ImportRecord;
use Throwable;

/**
 * Streams the stored file through {@see ProductRowImporter}, keeping the
 * report on the {@see ImportRecord} up to date. Used both inline (small
 * files) and from the queued job (large files).
 */
class ImportRunner
{
    public function run(ImportRecord $record): void
    {
        $record->update(['status' => 'processing', 'started_at' => now()]);

        $disk = Storage::disk(config('importgestionali.disk'));

        if (blank($record->stored_path) || ! $disk->exists($record->stored_path)) {
            $this->fail($record, __('pim.import.error.file_gone'));

            return;
        }

        $reader = app(SpreadsheetReader::class);
        $importer = ProductRowImporter::make($record->create_missing_terms, $record->replace_taxonomy_terms);
        $meta = $record->meta ?? [];
        $cap = (int) config('importgestionali.issues_cap', 500);

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $issues = [];
        $seenSkus = [];

        try {
            $rows = $reader->rows(
                $disk->path($record->stored_path),
                pathinfo($record->original_filename, PATHINFO_EXTENSION),
                $meta['delimiter'] ?? null,
                $meta['encoding'] ?? null,
            );

            foreach ($rows as $line => $row) {
                $outcome = $importer->import(
                    RowMapper::apply($record->mapping ?? [], $row),
                    $line,
                    $record->update_existing,
                    $seenSkus,
                );

                match ($outcome->action) {
                    RowOutcome::CREATED => $created++,
                    RowOutcome::UPDATED => $updated++,
                    RowOutcome::SKIPPED => $skipped++,
                    default => null,
                };

                if ($outcome->isSkip() && count($issues) < $cap) {
                    $issues[] = ['line' => $outcome->line, 'reason' => $outcome->reason];
                }

                foreach ($outcome->warnings as $warning) {
                    if (count($issues) < $cap) {
                        $issues[] = ['line' => $outcome->line, 'reason' => $warning];
                    }
                }

                if ((($created + $updated + $skipped) % 200) === 0) {
                    $record->update([
                        'created_count' => $created,
                        'updated_count' => $updated,
                        'skipped_count' => $skipped,
                    ]);
                }
            }
        } catch (UnreadableImportFile $e) {
            $this->fail($record, $e->getMessage());

            return;
        } catch (Throwable $e) {
            report($e);
            $this->fail($record, __('pim.import.error.unexpected'));

            return;
        }

        $record->update([
            'status' => 'completed',
            'created_count' => $created,
            'updated_count' => $updated,
            'skipped_count' => $skipped,
            'issues' => $issues,
            'finished_at' => now(),
        ]);
    }

    private function fail(ImportRecord $record, string $message): void
    {
        $record->update([
            'status' => 'failed',
            'error_message' => $message,
            'finished_at' => now(),
        ]);
    }
}
