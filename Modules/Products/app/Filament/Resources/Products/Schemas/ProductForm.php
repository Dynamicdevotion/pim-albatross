<?php

namespace Modules\Products\Filament\Resources\Products\Schemas;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Modules\Localization\Models\Language;
use Modules\Localization\Support\Locales;
use Modules\Pricing\Models\PriceList;
use Modules\Taxonomies\Models\TaxonomyTerm;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
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
                Repeater::make('prices')
                    ->label(__('pim.field.prices'))
                    ->relationship()
                    ->columns(2)
                    ->schema([
                        Select::make('price_list_id')
                            ->label(__('pim.field.price_list'))
                            ->options(fn (): array => PriceList::query()
                                ->orderByDesc('is_default')
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all())
                            ->required()
                            ->distinct()
                            ->selectablePlaceholder(false),
                        TextInput::make('price')
                            ->label(__('pim.field.price'))
                            ->numeric()
                            ->minValue(0)
                            ->required()
                            ->extraInputAttributes(['step' => '0.01']),
                    ])
                    ->itemLabel(fn (array $state): ?string => filled($state['price_list_id'] ?? null)
                        ? PriceList::find($state['price_list_id'])?->name.' — '.($state['price'] ?? '?')
                        : null)
                    ->addActionLabel(__('pim.action.add_price'))
                    ->reorderable(false)
                    ->defaultItems(0)
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
