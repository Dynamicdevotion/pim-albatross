<?php

namespace Modules\Pricing\Filament\Resources\PriceLists\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Modules\Pricing\Models\PriceList;

class PriceListForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('pim.field.name'))
                    ->required()
                    ->maxLength(255),
                Toggle::make('active')
                    ->label(__('pim.field.active'))
                    ->default(true),

                Section::make(__('pim.section.populate_prices'))
                    ->description(__('pim.section.populate_prices_hint'))
                    ->visible(fn (string $operation): bool => $operation === 'create')
                    ->columns(2)
                    ->schema([
                        Select::make('source_price_list_id')
                            ->label(__('pim.field.source_list'))
                            ->options(fn (): array => PriceList::query()
                                ->orderByDesc('is_default')
                                ->pluck('name', 'id')
                                ->all())
                            ->searchable()
                            ->live()
                            ->dehydrated(),
                        TextInput::make('adjustment_percent')
                            ->label(__('pim.field.adjustment_percent'))
                            ->numeric()
                            ->helperText(__('pim.helper.percent'))
                            ->visible(fn (Get $get): bool => filled($get('source_price_list_id')))
                            ->dehydrated(),
                    ]),
            ]);
    }
}
