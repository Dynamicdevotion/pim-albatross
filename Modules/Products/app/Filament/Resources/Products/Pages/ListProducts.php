<?php

namespace Modules\Products\Filament\Resources\Products\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Products\Filament\Resources\Products\ProductResource;
use Modules\SavedViews\Filament\Concerns\InteractsWithSavedViews;

class ListProducts extends ListRecords
{
    use InteractsWithSavedViews;

    protected static string $resource = ProductResource::class;

    protected string $view = 'products::filament.pages.list-products';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    // ---- saved views contract ----------------------------------------------

    public function savedViewResourceKey(): string
    {
        return 'products';
    }

    /**
     * Snapshot the applied table filters and the column-manager state
     * (visibility + order) exactly as the price grid snapshots its own toolbar.
     */
    public function captureViewState(): array
    {
        return [
            'filters' => $this->tableFilters ?? [],
            'columns' => $this->tableColumns,
        ];
    }

    public function applyViewState(array $state): void
    {
        $filters = $state['filters'] ?? [];

        // Filters are deferred (an Apply button), so the visible form is bound
        // to `tableDeferredFilters` while the query reads `tableFilters`.
        $this->tableDeferredFilters = $filters;
        $this->tableFilters = $filters;
        $this->getTableFiltersForm()->fill($filters);

        if (filled($state['columns'] ?? null)) {
            $this->applyTableColumnManager($state['columns']);
        }

        $this->resetPage();
    }
}
