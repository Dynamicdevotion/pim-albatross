<?php

namespace Modules\Products\Filament\Resources\Products\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Products\Filament\Resources\Products\Concerns\HandlesProductTranslations;
use Modules\Products\Filament\Resources\Products\ProductResource;

class CreateProduct extends CreateRecord
{
    use HandlesProductTranslations;

    protected static string $resource = ProductResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->extractTranslations($data);
    }

    protected function afterCreate(): void
    {
        $this->saveTranslations();
    }
}
