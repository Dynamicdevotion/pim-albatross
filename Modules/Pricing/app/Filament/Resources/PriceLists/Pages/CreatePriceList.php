<?php

namespace Modules\Pricing\Filament\Resources\PriceLists\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Pricing\Filament\Resources\PriceLists\Concerns\HandlesPricePopulation;
use Modules\Pricing\Filament\Resources\PriceLists\PriceListResource;

class CreatePriceList extends CreateRecord
{
    use HandlesPricePopulation;

    protected static string $resource = PriceListResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->extractPopulation($data);
    }

    protected function afterCreate(): void
    {
        $this->populatePrices($this->record);
    }
}
