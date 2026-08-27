<?php

namespace Modules\Products\Filament\Resources\Products\Schemas;

use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Modules\Localization\Models\Language;
use Modules\Localization\Support\Locales;
use Modules\Pricing\Support\ProductPriceMatrix;
use Modules\Products\Enums\ProductType;
use Modules\Products\Models\Product;
use Modules\Taxonomies\Models\TaxonomyTerm;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
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
                    ->getOptionLabelFromRecordUsing(fn (TaxonomyTerm $record): string =>
                        "{$record->taxonomy->name}: {$record->name}")
                    ->columnSpanFull(),
                self::pricesTable(),
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
                            RichEditor::make("translations.{$language->code}.description")
                                ->label(__('pim.field.description')),
                        ]))
                        ->all()),
            ]);
    }

    /**
     * A fixed row per active price list; only the price is editable. A blank
     * price means "no price on that list" — see ProductPriceMatrix.
     */
    public static function pricesTable(): Repeater
    {
        return Repeater::make('prices')
            ->label(__('pim.field.prices'))
            ->visible(fn (Get $get): bool => $get('type') !== ProductType::Variable->value)
            ->addable(false)
            ->deletable(false)
            ->reorderable(false)
            ->table([
                TableColumn::make(__('pim.field.price_list')),
                TableColumn::make(__('pim.field.price')),
            ])
            ->schema([
                Hidden::make('price_list_id'),
                TextInput::make('price_list_label')
                    ->hiddenLabel()
                    ->disabled()
                    ->dehydrated(false),
                TextInput::make('price')
                    ->hiddenLabel()
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(99999999.99)
                    ->extraInputAttributes(['step' => '0.01'])
                    ->placeholder('—'),
            ])
            ->default(fn (): array => ProductPriceMatrix::readItems())
            ->columnSpanFull();
    }
}
