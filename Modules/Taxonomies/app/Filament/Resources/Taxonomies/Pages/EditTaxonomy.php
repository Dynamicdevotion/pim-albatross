<?php

namespace Modules\Taxonomies\Filament\Resources\Taxonomies\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Modules\Taxonomies\Filament\Resources\Taxonomies\TaxonomyResource;

class EditTaxonomy extends EditRecord
{
    protected static string $resource = TaxonomyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
