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
                    ->required()
                    ->maxLength(255),
                Toggle::make('active')
                    ->default(true),

                Section::make('Populate prices from another list')
                    ->description('Optional. Copies every price from the chosen list into the new one, applying a percentage change.')
                    ->visible(fn (string $operation): bool => $operation === 'create')
                    ->columns(2)
                    ->schema([
                        Select::make('source_price_list_id')
                            ->label('Source list')
                            ->options(fn (): array => PriceList::query()
                                ->orderByDesc('is_default')
                                ->pluck('name', 'id')
                                ->all())
                            ->searchable()
                            ->live()
                            ->dehydrated(),
                        TextInput::make('adjustment_percent')
                            ->label('Adjustment %')
                            ->numeric()
                            ->helperText('e.g. 10 for +10%, -15 for −15%. Blank copies prices unchanged.')
                            ->visible(fn (Get $get): bool => filled($get('source_price_list_id')))
                            ->dehydrated(),
                    ]),
            ]);
    }
}
