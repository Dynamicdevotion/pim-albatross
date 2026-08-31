<?php

namespace Modules\ExportProdotti\Filament\Resources\ExportRecords\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\ExportProdotti\Filament\Resources\ExportRecords\ExportRecordResource;

class ListExportRecords extends ListRecords
{
    protected static string $resource = ExportRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
