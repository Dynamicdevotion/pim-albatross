<?php

namespace Modules\Pricing\Filament\Resources\PriceLists;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Modules\Pricing\Filament\Resources\PriceLists\Pages\CreatePriceList;
use Modules\Pricing\Filament\Resources\PriceLists\Pages\EditPriceList;
use Modules\Pricing\Filament\Resources\PriceLists\Pages\ListPriceLists;
use Modules\Pricing\Filament\Resources\PriceLists\Schemas\PriceListForm;
use Modules\Pricing\Filament\Resources\PriceLists\Tables\PriceListsTable;
use Modules\Pricing\Models\PriceList;

class PriceListResource extends Resource
{
    protected static ?string $model = PriceList::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|\UnitEnum|null $navigationGroup = 'Pricing';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('pim.resource.price_list.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('pim.resource.price_list.plural');
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('pim.nav.pricing');
    }

    public static function form(Schema $schema): Schema
    {
        return PriceListForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PriceListsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPriceLists::route('/'),
            'create' => CreatePriceList::route('/create'),
            'edit' => EditPriceList::route('/{record}/edit'),
        ];
    }
}
