<?php

namespace Modules\Taxonomies\Filament\Resources\Taxonomies\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Modules\Localization\Filament\Concerns\HandlesTranslatableName;
use Modules\Taxonomies\Filament\Resources\Taxonomies\TaxonomyResource;
use Modules\Taxonomies\Models\Taxonomy;

class EditTaxonomy extends EditRecord
{
    use HandlesTranslatableName;

    protected static string $resource = TaxonomyResource::class;

    protected function slugModelClass(): string
    {
        return Taxonomy::class;
    }

    protected function slugExistingKey(): int|string|null
    {
        return $this->record?->getKey();
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['translations'] = $this->nameTranslationsFor($this->record);

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->extractNameTranslations($data);
    }

    protected function afterSave(): void
    {
        $this->saveNameTranslations($this->record);
    }
}
