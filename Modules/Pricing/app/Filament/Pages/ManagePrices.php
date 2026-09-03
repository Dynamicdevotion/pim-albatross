<?php

namespace Modules\Pricing\Filament\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Modules\Localization\Support\Locales;
use Modules\Pricing\Models\PriceList;
use Modules\Pricing\Models\ProductPrice;
use Modules\Pricing\Support\PriceAdjuster;
use Modules\Products\Enums\ProductType;
use Modules\Products\Filament\Resources\Products\Tables\ProductsTable;
use Modules\Products\Models\Product;
use Modules\Products\Support\ProductListQuery;
use Modules\SavedViews\Filament\Concerns\InteractsWithSavedViews;
use Modules\Taxonomies\Models\Taxonomy;
use Modules\Taxonomies\Models\TaxonomyTerm;

/**
 * Excel-like editor for every product's price in one price list. A jspreadsheet
 * grid (drag select, paste from Excel, drag-fill) sits under a toolbar of
 * filters, per-user saved views, column toggles and bulk % actions.
 *
 * Rows are the priceable products — simple products and variants; the variable
 * container itself has no price and is left out. Variant rows are labelled
 * "— Parent › Variant" and ordered next to their siblings.
 *
 * The filter drawer reuses the exact same {@see ProductsTable} filter set as
 * the products list (search, type, status, missing translation, taxonomy,
 * stock — all backed by {@see ProductListQuery}, the single place that logic
 * lives), plus one filter of its own: "price presence", scoped to whichever
 * price list this page currently has selected rather than a filter-chosen
 * one. `HasTable`/`InteractsWithTable` are used purely for that filter engine
 * (drawer form, deferred apply/reset, active-filter badge) — the grid itself
 * is the jspreadsheet below, not a Filament table.
 */
class ManagePrices extends Page implements HasTable
{
    use InteractsWithSavedViews;
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|\UnitEnum|null $navigationGroup = 'Pricing';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Bulk price editing';

    protected static ?string $title = 'Bulk price editing';

    protected static ?string $slug = 'prices';

    protected string $view = 'pricing::filament.pages.manage-prices';

    private const ROW_CAP = 1000;

    public ?int $priceListId = null;

    /** @var list<string> */
    public array $visibleColumns = ['name', 'sku', 'status'];

    /** @var list<int> */
    public array $selectedProductIds = [];

    public static function canAccess(): bool
    {
        return PriceList::query()->exists();
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('pim.nav.pricing');
    }

    public static function getNavigationLabel(): string
    {
        return __('pim.page.bulk_prices.nav');
    }

    public function getTitle(): string
    {
        return __('pim.page.bulk_prices.title');
    }

    public function mount(): void
    {
        $this->priceListId = PriceList::query()->where('is_default', true)->value('id')
            ?? PriceList::query()->value('id');
    }

    /**
     * The shared products-list filter set (search, type, status, missing
     * translation, taxonomy, stock) plus this page's own price-presence
     * filter. Never rendered as a real table — see the class docblock.
     */
    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => $this->baseQuery())
            ->filtersLayout(FiltersLayout::Hidden)
            ->filtersFormColumns(2)
            ->filters([
                ProductsTable::searchFilter(),
                ProductsTable::typeFilter(),
                ProductsTable::statusFilter(),
                ProductsTable::missingTranslationFilter(),
                ProductsTable::taxonomyFilter(),
                $this->pricePresenceFilter(),
                ProductsTable::stockFilter(),
            ]);
    }

    /**
     * "Presenza prezzo": with/without + range, always evaluated against
     * whichever price list this page currently has selected — unlike the
     * products-list price filter, it has no price-list picker of its own.
     */
    protected function pricePresenceFilter(): Filter
    {
        return Filter::make('price')
            ->label(__('pim.filter.price'))
            ->columns(2)
            ->schema([
                Select::make('presence')
                    ->label(__('pim.filter.price_presence'))
                    ->native(false)
                    ->options([
                        'with' => __('pim.option.price.with'),
                        'without' => __('pim.option.price.without'),
                    ]),
                TextInput::make('min')->label(__('pim.field.price_min'))->numeric()->minValue(0),
                TextInput::make('max')->label(__('pim.field.price_max'))->numeric()->minValue(0),
            ])
            ->query(fn (Builder $query, array $data): Builder => ProductListQuery::price(
                $query,
                [...$data, 'price_list_id' => $this->priceListId],
            ))
            ->indicateUsing(function (array $data): ?string {
                $parts = [];

                if (($data['presence'] ?? null) === 'with') {
                    $parts[] = __('pim.option.price.with');
                }

                if (($data['presence'] ?? null) === 'without') {
                    $parts[] = __('pim.option.price.without');
                }

                if (filled($data['min'] ?? null) || filled($data['max'] ?? null)) {
                    $parts[] = trim(($data['min'] ?? '').' – '.($data['max'] ?? ''), ' –');
                }

                return $parts === [] ? null : __('pim.filter.price').': '.implode(', ', $parts);
            });
    }

    // ---- saved views contract -------------------------------------------------

    public function savedViewResourceKey(): string
    {
        return 'pricing.prices';
    }

    public function captureViewState(): array
    {
        return [
            'filters' => $this->tableFilters ?? [],
            'columns' => array_values($this->visibleColumns),
        ];
    }

    public function applyViewState(array $state): void
    {
        $filters = $state['filters'] ?? [];
        $this->tableDeferredFilters = $filters;
        $this->tableFilters = $filters;
        $this->getTableFiltersForm()->fill($filters);
        $this->visibleColumns = $state['columns'] ?? ['name', 'sku', 'status'];

        $this->refreshGrid();
    }

    // ---- reactive toolbar ---------------------------------------------------

    public function updated(string $property): void
    {
        if (in_array($property, ['priceListId', 'visibleColumns'], true)) {
            $this->refreshGrid();
        }
    }

    /**
     * Overrides {@see \Filament\Tables\Concerns\HasFilters::applyTableFilters()}
     * only to also refresh the grid: the grid container is `wire:ignore`d (so
     * jspreadsheet keeps ownership of its DOM), so it never picks up a filter
     * change from Livewire's normal re-render — it needs the same manual
     * `prices-grid-data` dispatch every other toolbar control uses.
     */
    public function applyTableFilters(): void
    {
        $this->tableFilters = $this->tableDeferredFilters;
        $this->handleTableFilterUpdates();
        $this->refreshGrid();
    }

    protected function refreshGrid(): void
    {
        $this->selectedProductIds = [];
        $this->dispatch('prices-grid-data', rows: $this->rows(), columns: array_values($this->visibleColumns));
    }

    /**
     * @return array<int, string>
     */
    public function priceListOptions(): array
    {
        return PriceList::query()
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public function columnCatalogue(): array
    {
        return [
            'name' => __('pim.field.name'),
            'sku' => __('pim.field.sku'),
            'status' => __('pim.field.status'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function gridHeaders(): array
    {
        return $this->columnCatalogue() + [
            'price' => __('pim.field.price'),
            'sale_price' => __('pim.field.sale_price'),
        ];
    }

    /**
     * Term id => "Taxonomy: Term".
     *
     * @return array<int, string>
     */
    public function categoryOptions(): array
    {
        $options = [];

        foreach (Taxonomy::query()->with(['translations', 'terms.translations'])->get() as $taxonomy) {
            foreach ($taxonomy->terms as $term) {
                $options[$term->id] = "{$taxonomy->name}: {$term->name}";
            }
        }

        return $options;
    }

    public function gridCapped(): bool
    {
        return $this->baseQuery()->count() > self::ROW_CAP;
    }

    /**
     * @return array<int, array{product_id: int, name: string, sku: string, status: string, price: string|null, sale_price: string|null}>
     */
    public function rows(): array
    {
        return $this->baseQuery()
            ->with([
                'translations',
                'parent.translations',
                'taxonomyTerms.translations',
                'prices' => fn ($query) => $query->where('price_list_id', $this->priceListId),
            ])
            ->limit(self::ROW_CAP)
            ->get()
            ->map(function (Product $product): array {
                $price = $product->prices->first();

                return [
                    'product_id' => $product->id,
                    'name' => $this->rowLabel($product),
                    'sku' => $product->sku,
                    'status' => $product->status,
                    'price' => $price?->price,
                    'sale_price' => $price?->sale_price,
                ];
            })
            ->all();
    }

    /**
     * A simple product shows its base name; a variant shows
     * "— Parent › distinguishing part" (own name if it differs from the
     * parent, otherwise its taxonomy terms, otherwise its SKU).
     */
    private function rowLabel(Product $product): string
    {
        $base = Locales::baseCode();
        $ownName = $product->translate($base)?->name;

        if (! $product->isVariant()) {
            return $ownName ?? $product->sku;
        }

        $parentName = $product->parent?->translate($base)?->name
            ?? $product->parent?->sku
            ?? '?';

        $distinct = $ownName !== null && $ownName !== $parentName
            ? $ownName
            : $product->taxonomyTerms
                ->map(fn (TaxonomyTerm $term): ?string => $term->translate($base)?->name)
                ->filter()
                ->implode(' · ');

        return '— '.$parentName.' › '.($distinct !== '' ? $distinct : $product->sku);
    }

    /**
     * The priceable-rows base scope (simple products and variants; the
     * variable container itself is never priced) plus every active filter,
     * applied through {@see ProductListQuery::apply()} — the same filter
     * clauses the products list and the export use. The `price` filter's
     * `price_list_id` is always forced to this page's own selector, never to
     * whatever a filter (live, restored from a saved view, or from session)
     * happens to carry, since "presence" only makes sense for the list this
     * grid is currently editing.
     *
     * @return Builder<Product>
     */
    protected function baseQuery(): Builder
    {
        $filters = $this->tableFilters ?? [];
        $filters['price'] = [...($filters['price'] ?? []), 'price_list_id' => $this->priceListId];

        $query = Product::query()
            // Priceable rows only: variable containers carry no price of their own.
            ->where('type', '!=', ProductType::Variable->value)
            // Keep a variant family together, then order by SKU.
            ->orderByRaw('COALESCE(parent_id, id)')
            ->orderByRaw('parent_id IS NOT NULL')
            ->orderBy('sku');

        return ProductListQuery::apply($query, $filters);
    }

    // ---- persistence from the grid ----------------------------------------

    /**
     * Each change carries only the field(s) actually edited in that batch
     * (`price` and/or `sale_price`) — editing one never blanks the other.
     *
     * @param  array<int, array{product_id: int, price?: mixed, sale_price?: mixed}>  $changes
     */
    public function saveCells(array $changes): void
    {
        DB::transaction(function () use ($changes): void {
            foreach ($changes as $change) {
                $this->writePrice((int) $change['product_id'], $change);
            }
        });
    }

    /**
     * @param  array{price?: mixed, sale_price?: mixed}  $fields
     */
    public function writePrice(int $productId, array $fields): void
    {
        if (array_key_exists('price', $fields)) {
            $price = ($fields['price'] === '' || $fields['price'] === null) ? null : round((float) $fields['price'], 2);

            if ($price === null) {
                // Clearing the price clears the whole row — a sale price
                // never makes sense without a regular one to discount from.
                ProductPrice::query()
                    ->where('product_id', $productId)
                    ->where('price_list_id', $this->priceListId)
                    ->delete();

                return;
            }

            ProductPrice::updateOrCreate(
                ['product_id' => $productId, 'price_list_id' => $this->priceListId],
                ['price' => $price],
            );
        }

        if (array_key_exists('sale_price', $fields)) {
            $salePrice = ($fields['sale_price'] === '' || $fields['sale_price'] === null)
                ? null
                : round((float) $fields['sale_price'], 2);

            // No-op if there is no price row yet to attach a sale price to.
            ProductPrice::query()
                ->where('product_id', $productId)
                ->where('price_list_id', $this->priceListId)
                ->update(['sale_price' => $salePrice]);
        }
    }

    // ---- bulk actions -----------------------------------------------------

    protected function assertSelection(): bool
    {
        if ($this->selectedProductIds === []) {
            Notification::make()->title(__('pim.notification.select_rows_first'))->warning()->send();

            return false;
        }

        return true;
    }

    public function setFixedPriceAction(): Action
    {
        return Action::make('setFixedPrice')
            ->label(__('pim.action.set_price'))
            ->icon('heroicon-o-currency-euro')
            ->schema([
                TextInput::make('price')->label(__('pim.field.price'))->numeric()->minValue(0)->required()
                    ->extraInputAttributes(['step' => '0.01']),
            ])
            ->action(function (array $data): void {
                if (! $this->assertSelection()) {
                    return;
                }

                $n = PriceAdjuster::setFixed($this->selectedProductIds, $this->priceListId, (float) $data['price']);
                $this->notifyDone(__('pim.notification.prices_set', ['count' => $n]));
            });
    }

    public function adjustSelectionAction(): Action
    {
        return Action::make('adjustSelection')
            ->label(__('pim.action.adjust_selection'))
            ->icon('heroicon-o-receipt-percent')
            ->schema([
                TextInput::make('percent')->label(__('pim.field.adjustment_percent'))->numeric()->required()
                    ->helperText(__('pim.helper.percent_short')),
            ])
            ->action(function (array $data): void {
                if (! $this->assertSelection()) {
                    return;
                }

                $n = PriceAdjuster::adjustProducts($this->selectedProductIds, $this->priceListId, (float) $data['percent']);
                $this->notifyDone(__('pim.notification.prices_adjusted_selection', ['count' => $n]));
            });
    }

    public function adjustCategoryAction(): Action
    {
        return Action::make('adjustCategory')
            ->label(__('pim.action.adjust_category'))
            ->icon('heroicon-o-tag')
            ->schema([
                Select::make('taxonomy_term_id')
                    ->label(__('pim.field.category'))
                    ->options(fn (): array => $this->categoryOptions())
                    ->searchable()
                    ->required(),
                TextInput::make('percent')->label(__('pim.field.adjustment_percent'))->numeric()->required()
                    ->helperText(__('pim.helper.adjust_category_scope')),
            ])
            ->action(function (array $data): void {
                $n = PriceAdjuster::adjustCategory(
                    (int) $data['taxonomy_term_id'],
                    $this->priceListId,
                    (float) $data['percent'],
                );
                $this->notifyDone(__('pim.notification.prices_adjusted_category', ['count' => $n]));
            });
    }

    protected function notifyDone(string $message): void
    {
        Notification::make()->title($message)->success()->send();
        $this->refreshGrid();
    }
}
