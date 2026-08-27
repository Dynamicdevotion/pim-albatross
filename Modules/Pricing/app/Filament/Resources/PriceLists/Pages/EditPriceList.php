<?php

namespace Modules\Pricing\Filament\Resources\PriceLists\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Modules\Pricing\Filament\Resources\PriceLists\PriceListResource;
use Modules\Pricing\Models\PriceList;

class EditPriceList extends EditRecord
{
    protected static string $resource = PriceListResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn (PriceList $record): bool => ! $record->is_default),
        ];
    }
}
