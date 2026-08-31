<?php

namespace Modules\Dashboard\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Modules\Pricing\Models\PriceList;
use Modules\Products\Filament\Resources\Products\ProductResource;
use Modules\Products\Support\ProductListQuery;

/**
 * Status numbers for the catalogue. Every stat is a live count from
 * {@see ProductListQuery} and links to the products list pre-filtered by the
 * exact same clause, so the number and the list it opens always match.
 */
class ProductOverviewStats extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $defaultPriceList = PriceList::query()->where('is_default', true)->first();

        $stats = [
            $this->stat(
                __('pim.dashboard.stat.active'),
                ['status' => ['value' => 'active']],
                'heroicon-m-check-circle',
                'success',
            ),
            $this->stat(
                __('pim.dashboard.stat.draft'),
                ['status' => ['value' => 'draft']],
                'heroicon-m-pencil-square',
                'gray',
            ),
            $this->stat(
                __('pim.dashboard.stat.archived'),
                ['status' => ['value' => 'archived']],
                'heroicon-m-archive-box',
                'warning',
            ),
            $this->stat(
                __('pim.dashboard.stat.stock_zero'),
                ['stock' => ['level' => 'zero']],
                'heroicon-m-exclamation-triangle',
                'danger',
            ),
            $this->stat(
                __('pim.dashboard.stat.missing_translation'),
                ['missing_translation' => ['value' => '*']],
                'heroicon-m-language',
                'warning',
            ),
        ];

        // "No price on the default list" only makes sense once a default list
        // exists — otherwise the price filter is a no-op and the number would
        // be misleading.
        if ($defaultPriceList !== null) {
            $filters = ['price' => ['price_list_id' => $defaultPriceList->id, 'presence' => 'without']];

            array_splice($stats, 3, 0, [
                Stat::make(
                    __('pim.dashboard.stat.no_price'),
                    (string) ProductListQuery::for($filters)->count(),
                )
                    ->description(__('pim.dashboard.stat.no_price_hint', ['list' => $defaultPriceList->name]))
                    ->descriptionIcon('heroicon-m-currency-euro')
                    ->color('warning')
                    ->url(ProductResource::getUrl('index', ['filters' => $filters])),
            ]);
        }

        return $stats;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function stat(string $label, array $filters, string $icon, string $color): Stat
    {
        return Stat::make($label, (string) ProductListQuery::for($filters)->count())
            ->descriptionIcon($icon)
            ->color($color)
            ->url(ProductResource::getUrl('index', ['filters' => $filters]));
    }
}
