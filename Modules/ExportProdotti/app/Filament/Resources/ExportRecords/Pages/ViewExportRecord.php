<?php

namespace Modules\ExportProdotti\Filament\Resources\ExportRecords\Pages;

use Filament\Resources\Pages\ViewRecord;
use Modules\ExportProdotti\Filament\Resources\ExportRecords\ExportRecordResource;

class ViewExportRecord extends ViewRecord
{
    protected static string $resource = ExportRecordResource::class;

    protected string $view = 'exportprodotti::filament.resources.view-export-record';

    protected function getHeaderActions(): array
    {
        return [
            ExportRecordResource::downloadAction(),
        ];
    }
}
