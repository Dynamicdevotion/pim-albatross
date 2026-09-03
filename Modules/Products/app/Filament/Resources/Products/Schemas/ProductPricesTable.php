<?php

namespace Modules\Products\Filament\Resources\Products\Schemas;

use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\TextInput;
use Modules\Pricing\Support\ProductPriceMatrix;

/**
 * The per-list price editor shared by the product form and the variant form:
 * a fixed table with one row per active price list, only the price editable,
 * no add/remove (price lists are managed in the Price Lists resource).
 *
 * A blank price means "no price on that list"; persistence — including deleting
 * the row when a previously-set price is cleared — is handled by
 * {@see ProductPriceMatrix::write()} in the owning page/action. The caller adds
 * ->visible()/->columnSpanFull() as needed.
 */
class ProductPricesTable
{
    public static function make(): Repeater
    {
        return Repeater::make('prices')
            ->label(__('pim.field.prices'))
            ->addable(false)
            ->deletable(false)
            ->reorderable(false)
            ->table([
                TableColumn::make(__('pim.field.price_list')),
                TableColumn::make(__('pim.field.price')),
                TableColumn::make(__('pim.field.sale_price')),
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
                TextInput::make('sale_price')
                    ->hiddenLabel()
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(99999999.99)
                    ->extraInputAttributes(['step' => '0.01'])
                    ->placeholder('—')
                    ->helperText(__('pim.helper.sale_price')),
            ])
            ->default(fn (): array => ProductPriceMatrix::readItems());
    }
}
