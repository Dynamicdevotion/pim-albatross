<?php

namespace Modules\ImportGestionali\Support;

use Illuminate\Support\Facades\Storage;
use Modules\ImportGestionali\Models\ImportRecord;
use Throwable;

/**
 * Streams the stored file through {@see ProductRowImporter}, keeping the
 * report on the {@see ImportRecord} up to date. Used both inline (small
 * files) and from the queued job (large files).
 *
 * When the mapping includes a "parent_sku" column the run switches to the
 * two-pass variable-product algorithm ({@see VariantImportPlan}, then the
 * containers, then the variants); otherwise it stays a single streaming pass
 * and behaves exactly as before.
 */
class ImportRunner
{
    /** @var array{created: int, updated: int, skipped: int, issues: list<array{line: int, reason: string}>} */
    private array $tally = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'issues' => []];

    private int $issuesCap = 500;

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
        $mapping = $record->mapping ?? [];

        $this->issuesCap = (int) config('importgestionali.issues_cap', 500);
        $this->tally = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'issues' => []];

        try {
            $rows = $reader->rows(
                $disk->path($record->stored_path),
                pathinfo($record->original_filename, PATHINFO_EXTENSION),
                $meta['delimiter'] ?? null,
                $meta['encoding'] ?? null,
            );

            if (in_array('parent_sku', $mapping, true)) {
                $this->runVariantAware($rows, $mapping, $importer, $record);
            } else {
                $this->runFlat($rows, $mapping, $importer, $record);
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
            'created_count' => $this->tally['created'],
            'updated_count' => $this->tally['updated'],
            'skipped_count' => $this->tally['skipped'],
            'issues' => $this->tally['issues'],
            'finished_at' => now(),
        ]);
    }

    /**
     * The historical single streaming pass — simple products only.
     *
     * @param  iterable<int, list<string>>  $rows
     * @param  array<int|string, string|null>  $mapping
     */
    private function runFlat(iterable $rows, array $mapping, ProductRowImporter $importer, ImportRecord $record): void
    {
        $seenSkus = [];

        foreach ($rows as $line => $row) {
            $this->record($importer->import(
                RowMapper::apply($mapping, $row),
                $line,
                $record->update_existing,
                $seenSkus,
            ));

            $this->maybeFlush($record);
        }
    }

    /**
     * Two-pass: buffer the mapped rows, classify them, create/convert the
     * containers, then create the variants pointing at the right container.
     * Row order in the file is irrelevant — the link is the SKU alone.
     *
     * @param  iterable<int, list<string>>  $rows
     * @param  array<int|string, string|null>  $mapping
     */
    private function runVariantAware(iterable $rows, array $mapping, ProductRowImporter $importer, ImportRecord $record): void
    {
        $buffered = [];

        foreach ($rows as $line => $row) {
            $buffered[$line] = RowMapper::apply($mapping, $row);
        }

        $plan = VariantImportPlan::build($buffered);
        $seenSkus = [];

        /** @var array<string, int|string> $containers  lower parent sku => container id, or 'blocked' */
        $containers = [];

        // -- Pass 2a: top-level rows in file order (simple products + container definitions) --
        foreach ($plan->topLevelLines as $line) {
            $mapped = $buffered[$line];

            if (isset($plan->duplicateTopLevel[$line])) {
                $this->pushIssue($line, __('pim.import.issue.sku_dup_in_file', [
                    'line' => $line,
                    'sku' => trim($mapped['sku'] ?? ''),
                    'first' => $plan->duplicateTopLevel[$line],
                ]));
                $this->tally['skipped']++;
                $this->maybeFlush($record);

                continue;
            }

            if ($plan->classification[$line] === VariantImportPlan::CONTAINER) {
                $outcome = $importer->importParent($mapped, $line, true, $record->update_existing, $seenSkus);
                $containers[mb_strtolower(trim($mapped['sku'] ?? ''))] = $outcome->isSkip()
                    ? ($outcome->code ?? 'blocked')
                    : ($outcome->productId ?? 'blocked');
            } else {
                $outcome = $importer->import($mapped, $line, $record->update_existing, $seenSkus);
            }

            $this->record($outcome);
            $this->maybeFlush($record);
        }

        // -- Parents referenced by variants but with no row of their own. A
        //    row-less parent produces no report line and no skip of its own:
        //    the affected variants each carry the explanation in pass 2b. --
        foreach ($plan->impliedParents() as $lowerSku => $rawSku) {
            $refLine = $this->firstVariantLineFor($plan, $lowerSku);
            $outcome = $importer->importParent(['sku' => $rawSku], $refLine, false, $record->update_existing, $seenSkus);

            if ($outcome->isSkip()) {
                $containers[$lowerSku] = $outcome->code ?? 'blocked';
            } else {
                $containers[$lowerSku] = $outcome->productId ?? 'blocked';
                $this->record($outcome);
            }

            $this->maybeFlush($record);
        }

        // -- Pass 2b: variant rows in file order --
        foreach ($plan->variantLines as $line) {
            $lowerParent = $plan->parentKeyByLine[$line];
            $target = $containers[$lowerParent] ?? 'parent_not_found';

            if (! is_int($target)) {
                $sku = $plan->referencedParents[$lowerParent] ?? $lowerParent;
                $key = match ($target) {
                    'parent_not_found' => 'pim.import.issue.parent_not_found',
                    'parent_is_variant' => 'pim.import.issue.parent_is_variant',
                    'parent_exists_update_off' => 'pim.import.issue.parent_exists_update_off',
                    default => 'pim.import.issue.variant_parent_blocked',
                };
                $this->pushIssue($line, __($key, ['line' => $line, 'sku' => $sku]));
                $this->tally['skipped']++;
                $this->maybeFlush($record);

                continue;
            }

            $this->record($importer->importVariant(
                $buffered[$line],
                $line,
                $target,
                $record->update_existing,
                $seenSkus,
            ));
            $this->maybeFlush($record);
        }
    }

    private function firstVariantLineFor(VariantImportPlan $plan, string $lowerParentSku): int
    {
        foreach ($plan->variantLines as $line) {
            if (($plan->parentKeyByLine[$line] ?? null) === $lowerParentSku) {
                return $line;
            }
        }

        return 0;
    }

    private function record(RowOutcome $outcome): void
    {
        match ($outcome->action) {
            RowOutcome::CREATED => $this->tally['created']++,
            RowOutcome::UPDATED => $this->tally['updated']++,
            RowOutcome::SKIPPED => $this->tally['skipped']++,
            default => null,
        };

        if ($outcome->isSkip()) {
            $this->pushIssue($outcome->line, (string) $outcome->reason);
        }

        foreach ($outcome->warnings as $warning) {
            $this->pushIssue($outcome->line, $warning);
        }
    }

    private function pushIssue(int $line, string $reason): void
    {
        if (count($this->tally['issues']) < $this->issuesCap) {
            $this->tally['issues'][] = ['line' => $line, 'reason' => $reason];
        }
    }

    private function maybeFlush(ImportRecord $record): void
    {
        $done = $this->tally['created'] + $this->tally['updated'] + $this->tally['skipped'];

        if ($done > 0 && ($done % 200) === 0) {
            $record->update([
                'created_count' => $this->tally['created'],
                'updated_count' => $this->tally['updated'],
                'skipped_count' => $this->tally['skipped'],
            ]);
        }
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
