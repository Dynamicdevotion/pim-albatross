<?php

namespace Modules\Products\Filament\Resources\Products\RelationManagers;

use Closure;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Modules\Localization\Models\Language;
use Modules\Localization\Models\ProductTranslation;
use Modules\Localization\Support\Locales;
use Modules\Pricing\Models\PriceList;
use Modules\Products\Enums\ProductType;
use Modules\Products\Filament\Resources\Products\Concerns\HandlesVariantPrices;
use Modules\Products\Filament\Resources\Products\Concerns\HandlesVariantTranslations;
use Modules\Products\Filament\Resources\Products\Schemas\ProductPricesTable;
use Modules\Products\Models\Product;
use Modules\Products\Support\ExistingImagePicker;
use Modules\Products\Support\VariantGenerator;
use Modules\Taxonomies\Models\Taxonomy;
use Modules\Taxonomies\Models\TaxonomyTerm;

/**
 * Manages the variant children of a variable product: an inline table
 * (sku / stock editable in place, default-list price shown) plus a
 * "Generate variants" wizard that builds one variant per combination of
 * chosen taxonomy values.
 */
class VariantsRelationManager extends RelationManager
{
    use HandlesVariantPrices;
    use HandlesVariantTranslations;

    protected static string $relationship = 'variants';

    protected static ?string $recordTitleAttribute = 'sku';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof Product && $ownerRecord->isVariable();
    }

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('pim.field.variants');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make('translations')
                ->columnSpanFull()
                ->tabs(fn (): array => Locales::active()
                    ->map(fn (Language $language): Tabs\Tab => Tabs\Tab::make(
                        $language->name.($language->is_base ? __('pim.field.base_suffix') : ''),
                    )->schema([
                        TextInput::make("translations.{$language->code}.name")
                            ->label(__('pim.field.name'))
                            ->maxLength(255)
                            ->required($language->is_base),
                        TextInput::make("translations.{$language->code}.slug")
                            ->label(__('pim.field.slug'))
                            ->maxLength(255)
                            ->helperText(__('pim.helper.slug_from_name_translated'))
                            ->rule(fn (?Product $record): Closure => static function (string $attribute, $value, Closure $fail) use ($language, $record): void {
                                $value = trim((string) $value);

                                if ($value === '') {
                                    return;
                                }

                                $taken = ProductTranslation::query()
                                    ->where('language_id', $language->id)
                                    ->where('slug', Str::slug($value))
                                    ->when($record, fn (Builder $q, Product $record): Builder => $q->where('product_id', '!=', $record->id))
                                    ->exists();

                                if ($taken) {
                                    $fail(__('pim.validation.slug_taken'));
                                }
                            }),
                        RichEditor::make("translations.{$language->code}.description")
                            ->label(__('pim.field.description')),
                        // --- SEO (translated, one set per language) ---
                        Section::make(__('pim.section.seo'))
                            ->columnSpanFull()
                            ->schema([
                                TextInput::make("translations.{$language->code}.meta_title")
                                    ->label(__('pim.field.meta_title'))
                                    ->maxLength(255)
                                    ->helperText(__('pim.helper.meta_title')),
                                Textarea::make("translations.{$language->code}.meta_description")
                                    ->label(__('pim.field.meta_description'))
                                    ->rows(2)
                                    ->maxLength(255)
                                    ->helperText(__('pim.helper.meta_description')),
                            ]),
                    ]))
                    ->all()),
            TextInput::make('sku')
                ->label(__('pim.field.sku'))
                ->required()
                ->maxLength(255)
                ->unique('products', 'sku', ignoreRecord: true),
            TextInput::make('barcode')
                ->label(__('pim.field.barcode'))
                ->maxLength(255)
                ->default(null),
            TextInput::make('stock')
                ->label(__('pim.field.stock'))
                ->numeric()
                ->minValue(0)
                ->default(0),
            Section::make(__('pim.section.shipping'))
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('weight')
                        ->label(__('pim.field.weight'))
                        ->numeric()
                        ->minValue(0)
                        ->suffix('kg'),
                    TextInput::make('length')
                        ->label(__('pim.field.length'))
                        ->numeric()
                        ->minValue(0)
                        ->suffix('cm'),
                    TextInput::make('width')
                        ->label(__('pim.field.width'))
                        ->numeric()
                        ->minValue(0)
                        ->suffix('cm'),
                    TextInput::make('height')
                        ->label(__('pim.field.height'))
                        ->numeric()
                        ->minValue(0)
                        ->suffix('cm'),
                ]),
            Section::make(__('pim.section.media'))
                ->columnSpanFull()
                ->schema([
                    SpatieMediaLibraryFileUpload::make('main_image')
                        ->label(__('pim.field.main_image'))
                        ->collection('main_image')
                        ->disk('public')
                        ->conversion('thumb')
                        ->image()
                        ->imageEditor()
                        ->panelLayout('grid')
                        ->imagePreviewHeight('130')
                        ->extraAttributes(['style' => 'max-width: 30rem'], merge: true)
                        ->acceptedFileTypes(Product::IMAGE_MIME_TYPES)
                        ->maxSize(5120)
                        ->helperText(__('pim.helper.variant_main_image'))
                        ->hintAction(ExistingImagePicker::hintAction('main_image', multiple: false)),
                    SpatieMediaLibraryFileUpload::make('gallery')
                        ->label(__('pim.field.gallery'))
                        ->collection('gallery')
                        ->disk('public')
                        ->conversion('thumb')
                        ->image()
                        ->multiple()
                        ->reorderable()
                        ->appendFiles()
                        ->panelLayout('grid')
                        ->imagePreviewHeight('130')
                        ->extraAttributes(['style' => 'max-width: 30rem'], merge: true)
                        ->acceptedFileTypes(Product::IMAGE_MIME_TYPES)
                        ->maxSize(5120)
                        ->hintAction(ExistingImagePicker::hintAction('gallery', multiple: true))
                        ->columnSpanFull(),
                ]),

            // --- Custom ---
            // TODO: sezione "Custom" (attributi personalizzati) — da
            // implementare qui quando il modello lo supporterà.
            Select::make('taxonomyTerms')
                ->label(__('pim.field.taxonomy_terms'))
                ->relationship('taxonomyTerms', 'name', fn (Builder $query): Builder => $query->with('taxonomy'))
                ->multiple()
                ->preload()
                ->searchable()
                ->getOptionLabelFromRecordUsing(fn (TaxonomyTerm $record): string => "{$record->taxonomy->name}: {$record->name}")
                ->columnSpanFull(),
            ProductPricesTable::make()
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        $baseListId = PriceList::query()->where('is_default', true)->value('id');

        return $table
            ->recordTitleAttribute('sku')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['translations', 'prices', 'media', 'parent.media']))
            ->columns([
                ImageColumn::make('main_image')
                    ->label(__('pim.field.image'))
                    ->getStateUsing(fn (Product $record): ?string => $record->getMainImageUrl('thumb'))
                    ->imageSize(40)
                    ->square()
                    ->defaultImageUrl(fn (): string => asset('images/placeholder-product.svg')),
                TextColumn::make('name_base')
                    ->label(__('pim.field.name'))
                    ->getStateUsing(fn (Product $record): ?string => $record->translate(Locales::baseCode())?->name),
                TextInputColumn::make('sku')
                    ->label(__('pim.field.sku'))
                    ->rules(['required', 'max:255']),
                TextInputColumn::make('stock')
                    ->label(__('pim.field.stock'))
                    ->type('number')
                    ->rules(['nullable', 'integer', 'min:0']),
                TextColumn::make('default_price')
                    ->label(__('pim.field.price'))
                    ->state(fn (Product $record): ?string => $record->prices->firstWhere('price_list_id', $baseListId)?->price)
                    ->formatStateUsing(fn (?string $state): ?string => $state === null ? null : number_format((float) $state, 2))
                    ->placeholder('—'),
                TextColumn::make('translated_locales')
                    ->label(__('pim.field.translations'))
                    ->badge()
                    ->getStateUsing(fn (Product $record): array => $record->translations
                        ->map(fn ($t): ?string => Locales::codeFor((int) $t->language_id))
                        ->filter()
                        ->unique()
                        ->map(fn (string $c): string => strtoupper($c))
                        ->values()
                        ->all())
                    ->placeholder('—'),
            ])
            ->headerActions([
                $this->generateVariantsAction(),
                CreateAction::make()
                    ->label(__('pim.action.add_variant'))
                    ->fillForm(function (): array {
                        // The parent's name/description/meta are a convenient
                        // starting point for a new variant, but its slug is
                        // not: copying it verbatim would always collide (two
                        // products can never share a slug), so it starts
                        // blank here just like sku starts as a distinct
                        // "-NEW" suffix rather than the parent's own sku.
                        $translations = $this->readVariantTranslations($this->getOwnerRecord());

                        foreach ($translations as $code => $row) {
                            unset($translations[$code]['slug']);
                        }

                        return [
                            'sku' => $this->getOwnerRecord()->sku.'-NEW',
                            'stock' => 0,
                            'translations' => $translations,
                        ];
                    })
                    ->mutateDataUsing(function (array $data): array {
                        $data['type'] = ProductType::Variant->value;

                        return $this->pullVariantPrices($this->pullVariantTranslations($data));
                    })
                    ->after(function (Product $record): void {
                        $this->saveVariantTranslations($record);
                        $this->saveVariantPrices($record);
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->mutateRecordDataUsing(fn (array $data, Product $record): array => [
                        ...$data,
                        'translations' => $this->readVariantTranslations($record),
                        'prices' => $this->readVariantPrices($record),
                    ])
                    ->mutateDataUsing(fn (array $data): array => $this->pullVariantPrices($this->pullVariantTranslations($data)))
                    ->after(function (Product $record): void {
                        $this->saveVariantTranslations($record);
                        $this->saveVariantPrices($record);
                    }),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    self::bulkSetStockAndDimensionsAction(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Sets stock/weight/length/width/height on the selected variants — a
     * field left blank in the form is not touched on any record (same
     * "blank means leave it alone" rule as the equivalent bulk action on the
     * main products list). Unlike that one, no row here is ever a variable
     * container — every record in this table is a `variant` — so there is
     * nothing to exclude or report separately.
     */
    protected static function bulkSetStockAndDimensionsAction(): BulkAction
    {
        return BulkAction::make('bulkSetStockAndDimensions')
            ->label(__('pim.action.bulk_set_stock_and_dimensions'))
            ->icon('heroicon-o-cube')
            ->modalDescription(__('pim.helper.bulk_dimensions_blank_skips_variant'))
            ->schema([
                TextInput::make('stock')
                    ->label(__('pim.field.stock'))
                    ->numeric()
                    ->minValue(0),
                TextInput::make('weight')
                    ->label(__('pim.field.weight'))
                    ->numeric()
                    ->minValue(0)
                    ->suffix('kg'),
                TextInput::make('length')
                    ->label(__('pim.field.length'))
                    ->numeric()
                    ->minValue(0)
                    ->suffix('cm'),
                TextInput::make('width')
                    ->label(__('pim.field.width'))
                    ->numeric()
                    ->minValue(0)
                    ->suffix('cm'),
                TextInput::make('height')
                    ->label(__('pim.field.height'))
                    ->numeric()
                    ->minValue(0)
                    ->suffix('cm'),
            ])
            ->action(function (Collection $records, array $data): void {
                $fields = collect($data)
                    ->only(['stock', 'weight', 'length', 'width', 'height'])
                    ->filter(fn (mixed $value): bool => filled($value))
                    ->all();

                if ($fields === []) {
                    Notification::make()
                        ->warning()
                        ->title(__('pim.notification.bulk_dimensions_nothing_to_set'))
                        ->send();

                    return;
                }

                $records->each(fn (Product $variant) => $variant->update($fields));

                Notification::make()
                    ->success()
                    ->title(trans_choice(
                        'pim.notification.stock_dimensions_bulk_updated',
                        $records->count(),
                        ['count' => $records->count()],
                    ))
                    ->send();
            })
            ->deselectRecordsAfterCompletion();
    }

    protected function generateVariantsAction(): Action
    {
        return Action::make('generateVariants')
            ->label(__('pim.action.generate_variants'))
            ->icon('heroicon-o-squares-plus')
            ->modalWidth('3xl')
            ->steps([
                Step::make(__('pim.field.variant_values'))
                    ->schema(fn (): array => $this->valueSelectionSchema())
                    ->afterValidation(fn (Get $get, Set $set) => $this->buildPreview($get, $set)),
                Step::make(__('pim.field.variants'))
                    ->schema([
                        Placeholder::make('too_many_notice')
                            ->hiddenLabel()
                            ->visible(fn (Get $get): bool => (bool) $get('_too_many'))
                            ->content(__('pim.notification.too_many_combinations', [
                                'count' => VariantGenerator::MAX_COMBINATIONS + 1,
                                'max' => VariantGenerator::MAX_COMBINATIONS,
                            ])),
                        Repeater::make('variants')
                            ->hiddenLabel()
                            ->addable(false)
                            ->deletable(false)
                            ->reorderable(false)
                            ->columns(3)
                            ->schema([
                                TextInput::make('label')
                                    ->label(__('pim.field.variant'))
                                    ->disabled()
                                    ->dehydrated(false),
                                TextInput::make('sku')
                                    ->label(__('pim.field.sku'))
                                    ->required()
                                    ->helperText(__('pim.helper.variant_sku')),
                                TextInput::make('name')
                                    ->label(__('pim.field.name'))
                                    ->required(),
                            ]),
                    ]),
            ])
            ->action(fn (array $data) => $this->runGeneration($data));
    }

    /**
     * @return array<int, Component>
     */
    protected function valueSelectionSchema(): array
    {
        $taxonomies = Taxonomy::query()->with(['translations', 'terms.translations'])->get();

        $components = [
            Placeholder::make('generate_hint')
                ->hiddenLabel()
                ->content(__('pim.helper.generate_variants')),
            Select::make('taxonomies')
                ->label(__('pim.field.participating_taxonomies'))
                ->multiple()
                ->live()
                ->options($taxonomies->mapWithKeys(fn (Taxonomy $t): array => [$t->id => $t->name])->all()),
        ];

        foreach ($taxonomies as $taxonomy) {
            $components[] = CheckboxList::make("terms.{$taxonomy->id}")
                ->label($taxonomy->name)
                ->options($taxonomy->terms->mapWithKeys(fn (TaxonomyTerm $term): array => [$term->id => $term->name])->all())
                ->columns(3)
                ->visible(fn (Get $get): bool => in_array(
                    (string) $taxonomy->id,
                    array_map('strval', $get('taxonomies') ?? []),
                    true,
                ));
        }

        return $components;
    }

    protected function buildPreview(Get $get, Set $set): void
    {
        $termsByTax = VariantGenerator::normalizeSelection($get('terms') ?? [], $get('taxonomies') ?? []);
        $combinations = VariantGenerator::combinations($termsByTax);

        if (count($combinations) > VariantGenerator::MAX_COMBINATIONS) {
            $set('_too_many', true);
            $set('variants', []);

            return;
        }

        $set('_too_many', false);

        $terms = $combinations === []
            ? collect()
            : TaxonomyTerm::query()
                ->whereIn('id', array_values(array_unique(array_merge(...$combinations))))
                ->with('translations')
                ->get()
                ->keyBy('id');

        $parent = $this->getOwnerRecord();
        $baseName = $parent->translate(Locales::baseCode())?->name ?? $parent->sku;

        $rows = [];

        foreach ($combinations as $combination) {
            $comboTerms = array_map(fn (int $id): ?TaxonomyTerm => $terms->get($id), $combination);

            $rows[] = [
                'label' => VariantGenerator::comboLabel($comboTerms),
                'sku' => VariantGenerator::proposedSku($parent->sku, $comboTerms),
                'name' => $baseName,
            ];
        }

        $set('variants', $rows);
    }

    /**
     * Turn the wizard payload (taxonomies + terms + edited preview rows) into
     * variant products via VariantGenerator, then report the outcome.
     *
     * @param  array<string, mixed>  $data
     */
    public function runGeneration(array $data): void
    {
        $parent = $this->getOwnerRecord();
        $termsByTax = VariantGenerator::normalizeSelection($data['terms'] ?? [], $data['taxonomies'] ?? []);
        $combinations = VariantGenerator::combinations($termsByTax);

        if ($combinations === []) {
            Notification::make()->warning()->title(__('pim.notification.variants_none_selected'))->send();

            return;
        }

        if (count($combinations) > VariantGenerator::MAX_COMBINATIONS) {
            Notification::make()->danger()->title(__('pim.notification.too_many_combinations', [
                'count' => count($combinations),
                'max' => VariantGenerator::MAX_COMBINATIONS,
            ]))->send();

            return;
        }

        $overrides = [];

        foreach (array_values($data['variants'] ?? []) as $index => $row) {
            $overrides[$index] = [
                'sku' => $row['sku'] ?? null,
                'name' => $row['name'] ?? null,
            ];
        }

        $result = app(VariantGenerator::class)->generate($parent, $termsByTax, $overrides);

        $title = $result['skipped'] > 0
            ? __('pim.notification.variants_generated_partial', [
                'created' => $result['created'],
                'skipped' => $result['skipped'],
            ])
            : __('pim.notification.variants_generated', ['count' => $result['created']]);

        Notification::make()->success()->title($title)->send();
    }
}
