<?php

namespace Modules\Taxonomies\Filament\Resources\Taxonomies\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Localization\Filament\Concerns\HandlesTranslatableName;
use Modules\Taxonomies\Filament\Resources\Taxonomies\TaxonomyResource;
use Modules\Taxonomies\Models\Taxonomy;

class CreateTaxonomy extends CreateRecord
{
    use HandlesTranslatableName;

    protected static string $resource = TaxonomyResource::class;

    protected function slugModelClass(): string
    {
        return Taxonomy::class;
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->extractNameTranslations($data);
    }

    protected function afterCreate(): void
    {
        $this->saveNameTranslations($this->record);
    }
}
