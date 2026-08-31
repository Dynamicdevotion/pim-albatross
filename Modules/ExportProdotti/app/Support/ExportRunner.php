<?php

namespace Modules\ExportProdotti\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\ExportProdotti\Enums\ExportColumn;
use Modules\ExportProdotti\Jobs\RunProductExport;
use Modules\ExportProdotti\Models\ExportRecord;
use Modules\Products\Models\Product;
use Modules\Products\Support\ProductListQuery;
use Throwable;

/**
 * Streams the filtered product set through {@see SpreadsheetWriter}. Used both
 * inline (small exports, straight to a download response) and from the queued
 * {@see RunProductExport} (large ones).
 *
 * Variant expansion: a `variable` container is written as its own row and then
 * one row per child variant, so the file mirrors the whole catalogue rather
 * than just the top-level list.
 */
class ExportRunner
{
    private const CHUNK = 200;

    /**
     * The product query an export runs on: the same top-level, filter-aware
     * query the list page builds ({@see ProductListQuery}), plus the eager
     * loads the row builder needs for prices and for variant expansion.
     *
     * @param  array<string, mixed>  $filters  the list page's `tableFilters` snapshot
     * @return Builder<Product>
     */
    public static function query(array $filters): Builder
    {
        return ProductListQuery::for($filters)
            ->with([
                'prices',
                'variants' => fn ($query) => $query->with(['translations', 'prices', 'media']),
            ]);
    }

    /**
     * Write the header and every matching row to $absolutePath. Returns the
     * number of data rows written (variants included, header excluded).
     *
     * @param  Builder<Product>  $query
     * @param  list<string>  $columns  column keys (any order; normalised here)
     */
    public function write(Builder $query, array $columns, string $format, string $absolutePath): int
    {
        $columns = ExportColumn::ordered($columns);
        $rowBuilder = ProductExportRow::make();
        $writer = SpreadsheetWriter::open($format, $absolutePath);

        try {
            $writer->writeRow(array_map(
                static fn (string $key): string => ExportColumn::from($key)->label(),
                $columns,
            ));

            $written = 0;

            $query->orderBy('id')->chunk(self::CHUNK, function ($products) use ($writer, $rowBuilder, $columns, &$written): void {
                foreach ($products as $product) {
                    $writer->writeRow($rowBuilder->values($product, $columns));
                    $written++;

                    if (! $product->isVariable()) {
                        continue;
                    }

                    foreach ($product->variants as $variant) {
                        $variant->setRelation('parent', $product);
                        $writer->writeRow($rowBuilder->values($variant, $columns, $product));
                        $written++;
                    }
                }
            });
        } finally {
            $writer->close();
        }

        return $written;
    }

    /**
     * Generate the file for a queued {@see ExportRecord} and record the outcome.
     */
    public function run(ExportRecord $record): void
    {
        $record->update(['status' => 'processing', 'started_at' => now()]);

        try {
            $disk = Storage::disk(config('exportprodotti.disk'));
            $format = SpreadsheetWriter::normalizeFormat($record->format);
            $relativePath = 'exports/'.Str::random(40).'.'.$format;
            $absolutePath = $disk->path($relativePath);

            if (! is_dir(dirname($absolutePath))) {
                mkdir(dirname($absolutePath), 0775, true);
            }

            $query = self::query($record->filters ?? []);
            $sort = is_array($record->sort) ? $record->sort : [];

            if (filled($sort['column'] ?? null)) {
                $query->orderBy($sort['column'], $sort['direction'] ?? 'asc');
            }

            $rowCount = $this->write($query, $record->columns ?? [], $format, $absolutePath);

            $record->update([
                'status' => 'completed',
                'stored_path' => $relativePath,
                'row_count' => $rowCount,
                'finished_at' => now(),
            ]);
        } catch (Throwable $e) {
            report($e);

            $record->update([
                'status' => 'failed',
                'error_message' => __('pim.export.error.unexpected'),
                'finished_at' => now(),
            ]);
        }
    }
}
