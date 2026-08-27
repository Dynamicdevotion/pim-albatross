<?php

namespace Modules\Taxonomies\Filament\Resources\Taxonomies\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Taxonomies\Filament\Resources\Taxonomies\TaxonomyResource;

class ListTaxonomies extends ListRecords
{
    protected static string $resource = TaxonomyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
