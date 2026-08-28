<?php

namespace Modules\ImportGestionali\Filament\Resources\ImportRecords\Pages;

use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Modules\ImportGestionali\Filament\Pages\ImportProducts;
use Modules\ImportGestionali\Filament\Resources\ImportRecords\ImportRecordResource;

class ViewImportRecord extends ViewRecord
{
    protected static string $resource = ImportRecordResource::class;

    protected string $view = 'importgestionali::filament.resources.view-import-record';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('new')
                ->label(__('pim.import.nav.upload'))
                ->icon('heroicon-o-arrow-up-tray')
                ->url(ImportProducts::getUrl()),
        ];
    }
}
