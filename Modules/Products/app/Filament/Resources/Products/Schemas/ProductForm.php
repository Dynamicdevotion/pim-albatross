<?php

namespace Modules\Products\Filament\Resources\Products\Schemas;

use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Modules\Localization\Models\Language;
use Modules\Localization\Support\Locales;
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
                            RichEditor::make("translations.{$language->code}.description")
                                ->label(__('pim.field.description')),
                        ]))
                        ->all()),
            ]);
    }
}
