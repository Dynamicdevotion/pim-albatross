<?php

namespace Modules\ImportGestionali\Filament\Pages;

use BackedEnum;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\ImportGestionali\Enums\TargetField;
use Modules\ImportGestionali\Filament\Resources\ImportRecords\ImportRecordResource;
use Modules\ImportGestionali\Jobs\RunProductImport;
use Modules\ImportGestionali\Models\ImportRecord;
use Modules\ImportGestionali\Support\FieldGuesser;
use Modules\ImportGestionali\Support\ImportRunner;
use Modules\ImportGestionali\Support\ProductRowImporter;
use Modules\ImportGestionali\Support\RowMapper;
use Modules\ImportGestionali\Support\RowOutcome;
use Modules\ImportGestionali\Support\SpreadsheetReader;
use Modules\ImportGestionali\Support\UnreadableImportFile;

/**
 * The import wizard: upload → map columns → preview → confirm.
 */
class ImportProducts extends Page
{
    protected string $view = 'importgestionali::filament.pages.import-products';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUpTray;

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'import-prodotti';

    /** @var array<string, mixed> */
    public ?array $data = [];

    /** @var list<string> */
    public array $fileHeader = [];

    /** @var list<list<string>> */
    public array $sampleRows = [];

    public ?int $totalRows = null;

    public ?string $delimiter = null;

    public ?string $encoding = null;

    public static function getNavigationGroup(): ?string
    {
        return __('pim.import.nav.group');
    }

    public static function getNavigationLabel(): string
    {
        return __('pim.import.nav.upload');
    }

    public function getTitle(): string
    {
        return __('pim.import.page.title');
    }

    public function mount(): void
    {
        $this->form->fill(['update_existing' => false]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Wizard::make([
                    Step::make(__('pim.import.step.upload'))
                        ->icon(Heroicon::OutlinedDocumentArrowUp)
                        ->schema([
                            FileUpload::make('file')
                                ->label(__('pim.import.field.file'))
                                ->disk(config('importgestionali.disk'))
                                ->directory('imports')
                                ->visibility('private')
                                ->storeFileNamesIn('file_original_names')
                                ->acceptedFileTypes([
                                    'text/csv', 'text/plain', 'application/csv',
                                    'application/vnd.ms-excel',
                                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                    'application/vnd.oasis.opendocument.spreadsheet',
                                ])
                                ->maxSize(((int) config('importgestionali.max_file_mb', 20)) * 1024)
                                ->required()
                                ->helperText(__('pim.import.help.file'))
                                ->afterStateUpdated(function (mixed $state): void {
                                    $this->inspectFile(is_string($state) ? $state : null);
                                }),
                        ])
                        ->afterValidation(function (): void {
                            if ($this->fileHeader === []) {
                                throw ValidationException::withMessages([
                                    'data.file' => __('pim.import.error.not_inspected'),
                                ]);
                            }
                        }),

                    Step::make(__('pim.import.step.map'))
                        ->icon(Heroicon::OutlinedArrowsRightLeft)
                        ->schema(fn (): array => $this->mappingComponents())
                        ->afterValidation(fn () => $this->assertMappingValid()),

                    Step::make(__('pim.import.step.preview'))
                        ->icon(Heroicon::OutlinedTableCells)
                        ->schema([
                            Placeholder::make('preview')
                                ->hiddenLabel()
                                ->content(fn () => view('importgestionali::filament.preview', [
                                    'rows' => $this->previewRows(),
                                    'total' => $this->totalRows,
                                    'updateExisting' => (bool) ($this->data['update_existing'] ?? false),
                                ])),
                        ]),
                ])
                    ->submitAction($this->confirmButton()),
            ]);
    }

    /**
     * Parse the just-uploaded file. A file-level failure is surfaced here and
     * clears the upload so the step's hidden validator keeps the user put.
     */
    public function inspectFile(?string $storedPath): void
    {
        $this->resetInspection();

        if (blank($storedPath)) {
            return;
        }

        $disk = Storage::disk(config('importgestionali.disk'));

        try {
            $shape = (new SpreadsheetReader(15))->inspect(
                $disk->path($storedPath),
                strtolower(pathinfo($storedPath, PATHINFO_EXTENSION)),
            );
        } catch (UnreadableImportFile $e) {
            $disk->delete($storedPath);
            $this->data['file'] = null;

            Notification::make()
                ->danger()
                ->title(__('pim.import.error.title'))
                ->body($e->getMessage())
                ->persistent()
                ->send();

            return;
        }

        $this->fileHeader = $shape->header;
        $this->sampleRows = $shape->sampleRows;
        $this->totalRows = $shape->dataRowCount;
        $this->delimiter = $shape->delimiter;
        $this->encoding = $shape->encoding;

        $this->data['mapping'] = FieldGuesser::forHeader($shape->header);
        $this->data['update_existing'] ??= false;
    }

    /**
     * @return array<int, Component>
     */
    protected function mappingComponents(): array
    {
        $selects = [];

        foreach ($this->fileHeader as $index => $header) {
            $samples = collect($this->sampleRows)
                ->take(3)
                ->map(fn (array $row): string => $row[$index] ?? '')
                ->filter()
                ->implode(' · ');

            $selects[] = Select::make("mapping.{$index}")
                ->label($header)
                ->native(false)
                ->options(TargetField::selectOptions())
                ->helperText($samples !== '' ? __('pim.import.help.sample', ['values' => Str::limit($samples, 60)]) : null);
        }

        return [
            Section::make(__('pim.import.step.map'))
                ->description(__('pim.import.help.map'))
                ->columns(2)
                ->schema($selects),
            Toggle::make('update_existing')
                ->label(__('pim.import.field.update_existing'))
                ->helperText(__('pim.import.help.update_existing'))
                ->default(false),
        ];
    }

    /**
     * @throws ValidationException
     */
    public function assertMappingValid(): void
    {
        $fields = array_values(array_filter(
            $this->data['mapping'] ?? [],
            fn ($value): bool => $value !== '' && $value !== null,
        ));

        if (! in_array('sku', $fields, true)) {
            throw ValidationException::withMessages([
                'data.mapping' => __('pim.import.error.sku_unmapped'),
            ]);
        }

        $duplicates = array_keys(array_filter(array_count_values($fields), fn (int $n): bool => $n > 1));

        if ($duplicates !== []) {
            throw ValidationException::withMessages([
                'data.mapping' => __('pim.import.error.field_mapped_twice', [
                    'field' => __('pim.import.field.'.$duplicates[0]),
                ]),
            ]);
        }
    }

    /**
     * @return list<array{line: int, values: array<string, string>, outcome: RowOutcome}>
     */
    public function previewRows(): array
    {
        if ($this->fileHeader === []) {
            return [];
        }

        $mapping = $this->data['mapping'] ?? [];
        $updateExisting = (bool) ($this->data['update_existing'] ?? false);
        $importer = ProductRowImporter::make();
        $seen = [];
        $out = [];

        foreach (array_slice($this->sampleRows, 0, (int) config('importgestionali.preview_rows', 10)) as $i => $row) {
            $line = $i + 2;
            $mapped = RowMapper::apply($mapping, $row);
            $out[] = [
                'line' => $line,
                'values' => $mapped,
                'outcome' => $importer->import($mapped, $line, $updateExisting, $seen, dryRun: true),
            ];
        }

        return $out;
    }

    public function import(): void
    {
        // Each wizard step was validated on the way here; read the collected
        // state directly rather than re-running the whole form validation,
        // which would re-check the (already consumed) upload field.
        $state = $this->data;
        $this->assertMappingValid();

        $storedPath = $state['file'] ?? null;

        if (blank($storedPath) || $this->fileHeader === []) {
            Notification::make()->danger()->title(__('pim.import.error.title'))->body(__('pim.import.error.not_inspected'))->send();

            return;
        }

        $originalName = $state['file_original_names'][$storedPath] ?? basename($storedPath);

        $record = ImportRecord::create([
            'user_id' => auth()->id(),
            'original_filename' => $originalName,
            'stored_path' => $storedPath,
            'status' => 'pending',
            'update_existing' => (bool) ($state['update_existing'] ?? false),
            'mapping' => collect($state['mapping'] ?? [])
                ->map(fn ($value) => $value === '' ? null : $value)
                ->all(),
            'meta' => [
                'header' => $this->fileHeader,
                'delimiter' => $this->delimiter,
                'encoding' => $this->encoding,
            ],
            'total_rows' => $this->totalRows,
        ]);

        $inlineMax = (int) config('importgestionali.inline_max_rows', 300);

        if (($this->totalRows ?? PHP_INT_MAX) <= $inlineMax) {
            app(ImportRunner::class)->run($record);
            Notification::make()->success()->title(__('pim.import.notify.done'))->send();
        } else {
            RunProductImport::dispatch($record);
            Notification::make()->success()->title(__('pim.import.notify.queued'))->send();
        }

        $this->redirect(ImportRecordResource::getUrl('view', ['record' => $record]));
    }

    protected function confirmButton(): Htmlable
    {
        return new HtmlString(Blade::render(
            '<x-filament::button type="submit" size="sm" icon="heroicon-o-check">{{ __(\'pim.import.confirm\') }}</x-filament::button>',
        ));
    }

    protected function resetInspection(): void
    {
        $this->fileHeader = [];
        $this->sampleRows = [];
        $this->totalRows = null;
        $this->delimiter = null;
        $this->encoding = null;
    }
}
