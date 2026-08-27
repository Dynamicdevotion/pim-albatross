<?php

namespace Modules\Products\Filament\Resources\Products\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Modules\Products\Filament\Resources\Products\Concerns\HandlesProductTranslations;
use Modules\Products\Filament\Resources\Products\ProductResource;

class EditProduct extends EditRecord
{
    use HandlesProductTranslations;

    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return $this->fillTranslations($data);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->extractTranslations($data);
    }

    protected function afterSave(): void
    {
        $this->saveTranslations();
    }
}
