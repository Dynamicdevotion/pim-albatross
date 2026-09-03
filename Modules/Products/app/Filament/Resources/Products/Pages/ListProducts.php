<?php

namespace Modules\Products\Filament\Resources\Products\Pages;

use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;
use Modules\ExportProdotti\Enums\ExportColumn;
use Modules\ExportProdotti\Filament\Resources\ExportRecords\ExportRecordResource;
use Modules\ExportProdotti\Jobs\RunProductExport;
use Modules\ExportProdotti\Models\ExportRecord;
use Modules\ExportProdotti\Support\ExportRunner;
use Modules\ExportProdotti\Support\SpreadsheetWriter;
use Modules\Products\Filament\Resources\Products\ProductResource;
use Modules\Products\Support\ProductListQuery;
use Modules\SavedViews\Filament\Concerns\InteractsWithSavedViews;

class ListProducts extends ListRecords
{
    use InteractsWithSavedViews;

    protected static string $resource = ProductResource::class;

    protected string $view = 'products::filament.pages.list-products';

    /**
     * Products-list column name => export column key. Columns the export has
     * no equivalent for (type, translations, terms, timestamps, …) are simply
     * absent; export-only columns (description, price, gallery_urls) have no
     * list column and so are never pre-selected.
     */
    private const EXPORT_COLUMN_MAP = [
        'sku' => 'sku',
        'name_base' => 'name',
        'stock' => 'stock',
        'weight' => 'weight',
        'length' => 'length',
        'width' => 'width',
        'height' => 'height',
        'status' => 'status',
        'main_image' => 'image_url',
    ];

    /**
     * Fallback pre-selection when none of the visible list columns maps to an
     * exportable one.
     */
    private const EXPORT_COLUMN_FALLBACK = ['sku', 'name', 'price', 'stock', 'status'];

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    // ---- export ----------------------------------------------------------

    /**
     * "Esporta" — rendered next to the Filters / Columns controls in the page
     * view. Opens a slide-over to pick the format and columns. Every export —
     * inline or queued — gets an {@see ExportRecord}; only how it is *run*
     * differs: past the row threshold it is queued via
     * {@see RunProductExport} and the user is sent to its report page,
     * otherwise {@see ExportRunner::run()} runs synchronously and the
     * generated file is streamed back immediately.
     */
    public function exportAction(): Action
    {
        return Action::make('export')
            ->label(__('pim.export.action.trigger'))
            ->icon('heroicon-m-arrow-down-tray')
            ->color('gray')
            ->slideOver()
            ->modalHeading(__('pim.export.modal.heading'))
            ->modalDescription(__('pim.export.modal.description'))
            ->modalSubmitActionLabel(__('pim.export.modal.submit'))
            ->fillForm(fn (): array => [
                'format' => SpreadsheetWriter::FORMAT_XLSX,
                'columns' => $this->defaultExportColumns(),
            ])
            ->schema([
                Radio::make('format')
                    ->label(__('pim.export.field.format'))
                    ->options([
                        SpreadsheetWriter::FORMAT_XLSX => __('pim.export.format.xlsx'),
                        SpreadsheetWriter::FORMAT_CSV => __('pim.export.format.csv'),
                    ])
                    ->required(),
                CheckboxList::make('columns')
                    ->label(__('pim.export.field.columns'))
                    ->options(ExportColumn::options())
                    ->columns(2)
                    ->bulkToggleable()
                    ->required(),
                Placeholder::make('summary')
                    ->hiddenLabel()
                    ->content(fn (): string => trans_choice(
                        'pim.export.modal.summary',
                        $this->exportMatchCount(),
                        ['count' => $this->exportMatchCount()],
                    )),
            ])
            ->action(function (array $data) {
                $columns = ExportColumn::ordered($data['columns'] ?? []);
                $format = SpreadsheetWriter::normalizeFormat($data['format'] ?? null);
                $filters = $this->tableFilters ?? [];
                $sort = $this->exportSort();
                $count = $this->exportMatchCount();
                $filename = 'export-prodotti-'.now()->format('Ymd-His').'.'.$format;

                $record = ExportRecord::create([
                    'user_id' => auth()->id(),
                    'format' => $format,
                    'columns' => $columns,
                    'filters' => $filters,
                    'sort' => $sort,
                    'status' => 'pending',
                    'total_rows' => $count,
                    'original_filename' => $filename,
                ]);

                if ($count > (int) config('exportprodotti.inline_max_rows', 1000)) {
                    RunProductExport::dispatch($record);

                    Notification::make()
                        ->title(__('pim.export.notify.queued'))
                        ->success()
                        ->send();

                    $this->redirect(ExportRecordResource::getUrl('view', ['record' => $record]));

                    return null;
                }

                app(ExportRunner::class)->run($record);
                $record->refresh();

                if ($record->isFailed()) {
                    Notification::make()
                        ->title(__('pim.export.notify.failed'))
                        ->danger()
                        ->send();

                    $this->redirect(ExportRecordResource::getUrl('view', ['record' => $record]));

                    return null;
                }

                return Storage::disk(config('exportprodotti.disk'))
                    ->download($record->stored_path, $record->original_filename);
            });
    }

    /**
     * Pre-tick the columns currently visible in the list (mapped to their
     * export equivalents), so the user starts from a sensible state.
     *
     * @return list<string>
     */
    public function defaultExportColumns(): array
    {
        $visible = collect($this->tableColumns)
            ->filter(fn (array $item): bool => ($item['type'] ?? null) === 'column'
                && (bool) ($item['isToggled'] ?? false))
            ->pluck('name')
            ->map(fn (string $name): ?string => self::EXPORT_COLUMN_MAP[$name] ?? null)
            ->filter()
            ->all();

        $mapped = ExportColumn::ordered($visible);

        return $mapped !== [] ? $mapped : self::EXPORT_COLUMN_FALLBACK;
    }

    /**
     * How many top-level products match the filters currently applied to the
     * list (pagination ignored). Variant rows are added on top at write time.
     */
    public function exportMatchCount(): int
    {
        return ProductListQuery::for($this->tableFilters ?? [])->count();
    }

    /**
     * The list's current sort, as stored on an ExportRecord and replayed by
     * the runner. Null when the table is in its default (unsorted) order.
     *
     * @return array{column: string, direction: string}|null
     */
    protected function exportSort(): ?array
    {
        $column = $this->getTableSortColumn();

        if ($column === null) {
            return null;
        }

        return [
            'column' => $column,
            'direction' => $this->getTableSortDirection() ?? 'asc',
        ];
    }

    // ---- saved views contract ----------------------------------------------

    public function savedViewResourceKey(): string
    {
        return 'products';
    }

    /**
     * Snapshot the applied table filters and the column-manager state
     * (visibility + order) exactly as the price grid snapshots its own toolbar.
     */
    public function captureViewState(): array
    {
        return [
            'filters' => $this->tableFilters ?? [],
            'columns' => $this->tableColumns,
        ];
    }

    public function applyViewState(array $state): void
    {
        $filters = $state['filters'] ?? [];

        // Filters are deferred (an Apply button), so the visible form is bound
        // to `tableDeferredFilters` while the query reads `tableFilters`.
        $this->tableDeferredFilters = $filters;
        $this->tableFilters = $filters;
        $this->getTableFiltersForm()->fill($filters);

        if (filled($state['columns'] ?? null)) {
            $this->applyTableColumnManager($state['columns']);
        }

        $this->resetPage();
    }
}
