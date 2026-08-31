<?php

namespace Modules\WooSync\Filament\Resources\WooSyncRuns\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\WooSync\Filament\Resources\WooSyncRuns\WooSyncRunResource;

class ListWooSyncRuns extends ListRecords
{
    protected static string $resource = WooSyncRunResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
