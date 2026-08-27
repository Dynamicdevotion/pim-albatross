<?php

namespace Modules\Products\Filament\Resources\Products\Pages;

use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Modules\Products\Enums\ProductType;
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
        $this->guardTypeChange($data);

        return $this->extractTranslations($data);
    }

    protected function afterSave(): void
    {
        $this->saveTranslations();
    }

    /**
     * Friendly stop for the "variable → other type while variants exist" case,
     * so the user sees a notification instead of the model's exception page.
     *
     * @param  array<string, mixed>  $data
     */
    protected function guardTypeChange(array $data): void
    {
        $newType = $data['type'] ?? $this->record->type?->value;

        if ($this->record->isVariable()
            && $newType !== ProductType::Variable->value
            && $this->record->variants()->exists()
        ) {
            Notification::make()
                ->danger()
                ->title(__('pim.validation.type_locked_has_variants'))
                ->send();

            $this->halt();
        }
    }
}
