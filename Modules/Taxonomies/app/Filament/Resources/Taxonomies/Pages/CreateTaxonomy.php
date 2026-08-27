<?php

namespace Modules\Taxonomies\Filament\Resources\Taxonomies\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Taxonomies\Filament\Resources\Taxonomies\TaxonomyResource;

class CreateTaxonomy extends CreateRecord
{
    protected static string $resource = TaxonomyResource::class;
}
