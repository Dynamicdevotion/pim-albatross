<?php

namespace Modules\WooSync\Filament\Resources\WooSyncRuns\Pages;

use Filament\Resources\Pages\ViewRecord;
use Modules\WooSync\Filament\Resources\WooSyncRuns\WooSyncRunResource;

class ViewWooSyncRun extends ViewRecord
{
    protected static string $resource = WooSyncRunResource::class;

    protected string $view = 'woosync::filament.resources.view-woo-sync-run';
}
