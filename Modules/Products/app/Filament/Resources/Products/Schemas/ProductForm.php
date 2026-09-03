<?php

namespace Modules\Products\Filament\Resources\Products\Schemas;

use Closure;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Modules\Localization\Models\Language;
use Modules\Localization\Models\ProductTranslation;
use Modules\Localization\Support\Locales;
use Modules\Products\Enums\ProductType;
use Modules\Products\Models\Product;
use Modules\Products\Support\ExistingImagePicker;
use Modules\Products\Support\RelatedProductPicker;
use Modules\Taxonomies\Models\TaxonomyTerm;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // --- main structure: sku / name / description / price / stock /
                //     taxonomies stay here, outside the grouped sections below ---
                Select::make('type')
                    ->label(__('pim.field.type'))
                    ->options(collect(ProductType::cases())
                        ->mapWithKeys(fn (ProductType $type): array => [$type->value => $type->getLabel()])
                        ->all())
                    ->default(ProductType::Simple->value)
                    ->required()
                    ->native(false)
                    ->live()
                    ->dehydrated()
                    // Locked once the container has variants — clear them first
                    // (enforced again by Product's saving guard).
                    ->disabled(fn (?Product $record): bool => $record !== null
                        && $record->isVariable()
                        && $record->variants()->exists())
                    ->helperText(fn (Get $get): ?string => $get('type') === ProductType::Variable->value
                        ? __('pim.helper.variable_no_price')
                        : null),
                TextInput::make('sku')
                    ->label(__('pim.field.sku'))
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                TextInput::make('barcode')
                    ->label(__('pim.field.barcode'))
                    ->maxLength(255)
                    ->default(null),
                TextInput::make('external_id')
                    ->label(__('pim.field.external_id'))
                    ->maxLength(255)
                    ->default(null),
                Select::make('status')
                    ->label(__('pim.field.status'))
                    ->required()
                    ->default('draft')
                    ->native(false)
                    ->options([
                        'draft' => __('pim.option.status.draft'),
                        'active' => __('pim.option.status.active'),
                        'archived' => __('pim.option.status.archived'),
                    ]),
                TextInput::make('stock')
                    ->label(__('pim.field.stock'))
                    ->numeric()
                    ->minValue(0)
                    ->default(0)
                    ->visible(fn (Get $get): bool => $get('type') !== ProductType::Variable->value),

                // --- Shipping ---
                Section::make(__('pim.section.shipping'))
                    ->visible(fn (Get $get): bool => $get('type') !== ProductType::Variable->value)
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

                // --- Images ---
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
                    ->relationship(
                        'taxonomyTerms',
                        'name',
                        fn (Builder $query): Builder => $query->with('taxonomy'),
                    )
                    ->multiple()
                    ->preload()
                    ->searchable()
                    ->getOptionLabelFromRecordUsing(fn (TaxonomyTerm $record): string => "{$record->taxonomy->name}: {$record->name}")
                    ->columnSpanFull(),

                // --- Related products ---
                Section::make(__('pim.section.related_products'))
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        RelatedProductPicker::make('upsell_ids', __('pim.field.upsells')),
                        RelatedProductPicker::make('cross_sell_ids', __('pim.field.cross_sells')),
                    ]),

                ProductPricesTable::make()
                    ->visible(fn (Get $get): bool => $get('type') !== ProductType::Variable->value)
                    ->columnSpanFull(),
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
            ]);
    }
}
