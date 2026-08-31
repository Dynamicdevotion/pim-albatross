<?php

namespace Modules\Dashboard\Filament\Widgets;

use Filament\Widgets\Widget;
use Modules\ImportGestionali\Filament\Resources\ImportRecords\ImportRecordResource;
use Modules\ImportGestionali\Models\ImportRecord;

/**
 * The rows dropped by the most recent import that skipped any, with a direct
 * link to that run's report. (Skipped rows are an import-only concept — the
 * export has none — so this always points at ImportRecords.)
 */
class RecentImportIssues extends Widget
{
    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'dashboard::widgets.recent-import-issues';

    /** How many issue lines to show before "+N altre". */
    private const LIMIT = 10;

    protected function getViewData(): array
    {
        $record = ImportRecord::query()
            ->where('status', 'completed')
            ->where('skipped_count', '>', 0)
            ->latest()
            ->first();

        $issues = collect($record?->issues ?? []);

        return [
            'record' => $record,
            'issues' => $issues->take(self::LIMIT)->all(),
            'remaining' => max(0, $issues->count() - self::LIMIT),
            'reportUrl' => $record
                ? ImportRecordResource::getUrl('view', ['record' => $record])
                : null,
        ];
    }
}
