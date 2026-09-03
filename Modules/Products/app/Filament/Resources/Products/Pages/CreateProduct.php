<?php

namespace Modules\Products\Filament\Resources\Products\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Products\Filament\Resources\Products\Concerns\HandlesProductPrices;
use Modules\Products\Filament\Resources\Products\Concerns\HandlesProductRelations;
use Modules\Products\Filament\Resources\Products\Concerns\HandlesProductTranslations;
use Modules\Products\Filament\Resources\Products\ProductResource;

class CreateProduct extends CreateRecord
{
    use HandlesProductPrices;
    use HandlesProductRelations;
    use HandlesProductTranslations;

    protected static string $resource = ProductResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->extractRelations($this->extractPrices($this->extractTranslations($data)));
    }

    protected function afterCreate(): void
    {
        $this->saveTranslations();
        $this->savePrices();
        $this->saveRelations();
    }
}
