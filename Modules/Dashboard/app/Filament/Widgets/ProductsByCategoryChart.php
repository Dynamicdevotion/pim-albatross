<?php

namespace Modules\Dashboard\Filament\Widgets;

use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Modules\Products\Filament\Resources\Products\ProductResource;
use Modules\Products\Support\ProductListQuery;
use Modules\Taxonomies\Models\Taxonomy;
use Modules\Taxonomies\Models\TaxonomyTerm;

/**
 * Product distribution across the terms of the "Categoria" taxonomy.
 *
 * Each bar's value is counted through {@see ProductListQuery} with the same
 * `taxonomy_terms` clause the bar links to (subtree expansion included), so
 * clicking a bar opens the products list showing exactly that many rows.
 */
class ProductsByCategoryChart extends ChartWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = ['default' => 'full', 'lg' => 1];

    protected ?string $maxHeight = '280px';

    public function getHeading(): string
    {
        return __('pim.dashboard.chart.category.heading');
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $taxonomy = Taxonomy::query()
            ->where('slug', 'categoria')
            ->with(['terms.translations'])
            ->first();

        if ($taxonomy === null) {
            return ['datasets' => [['label' => __('pim.dashboard.chart.category.dataset'), 'data' => []]], 'labels' => []];
        }

        $rows = $taxonomy->terms
            ->map(fn (TaxonomyTerm $term): array => [
                'label' => $term->name ?? $term->slug,
                'count' => ProductListQuery::for(['taxonomy_terms' => ['terms' => [$term->id]]])->count(),
                'url' => ProductResource::getUrl('index', [
                    'filters' => ['taxonomy_terms' => ['terms' => [$term->id]]],
                ]),
            ])
            ->sortByDesc('count')
            ->values();

        return [
            'datasets' => [[
                'label' => __('pim.dashboard.chart.category.dataset'),
                'data' => $rows->pluck('count')->all(),
                'urls' => $rows->pluck('url')->all(),
            ]],
            'labels' => $rows->pluck('label')->all(),
        ];
    }

    protected function getOptions(): RawJs
    {
        return RawJs::make(<<<'JS'
        {
            onClick: (event, elements, chart) => {
                if (! elements.length) {
                    return;
                }
                const url = chart.data.datasets[0].urls?.[elements[0].index];
                if (url) {
                    window.location.href = url;
                }
            },
            plugins: {
                legend: { display: false },
            },
            scales: {
                y: { beginAtZero: true, ticks: { precision: 0 } },
            },
        }
        JS);
    }
}
