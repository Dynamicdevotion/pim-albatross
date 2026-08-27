<?php

namespace Modules\Pricing\Filament\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Modules\Localization\Support\Locales;
use Modules\Pricing\Models\PriceList;
use Modules\Pricing\Models\ProductPrice;
use Modules\Pricing\Support\PriceAdjuster;
use Modules\Products\Enums\ProductType;
use Modules\Products\Models\Product;
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
 */
class ManagePrices extends Page
{
    use InteractsWithSavedViews;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|\UnitEnum|null $navigationGroup = 'Pricing';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Bulk price editing';

    protected static ?string $title = 'Bulk price editing';

    protected static ?string $slug = 'prices';

    protected string $view = 'pricing::filament.pages.manage-prices';

    private const ROW_CAP = 1000;

    public ?int $priceListId = null;

    public string $search = '';

    public ?string $hasPrice = null;      // 'yes' | 'no' | null

    public ?int $categoryTermId = null;

    public ?string $variantScope = null;  // 'variants' | 'simple' | null

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

    // ---- saved views contract -------------------------------------------------

    public function savedViewResourceKey(): string
    {
        return 'pricing.prices';
    }

    public function captureViewState(): array
    {
        return [
            'filters' => [
                'search' => $this->search,
                'hasPrice' => $this->hasPrice,
                'categoryTermId' => $this->categoryTermId,
                'variantScope' => $this->variantScope,
            ],
            'columns' => array_values($this->visibleColumns),
        ];
    }

    public function applyViewState(array $state): void
    {
        $filters = $state['filters'] ?? [];
        $this->search = (string) ($filters['search'] ?? '');
        $this->hasPrice = $filters['hasPrice'] ?? null;
        $this->categoryTermId = isset($filters['categoryTermId']) ? (int) $filters['categoryTermId'] : null;
        $this->variantScope = $filters['variantScope'] ?? null;
        $this->visibleColumns = $state['columns'] ?? ['name', 'sku', 'status'];

        $this->refreshGrid();
    }

    // ---- reactive toolbar ---------------------------------------------------

    public function updated(string $property): void
    {
        if (in_array($property, ['priceListId', 'search', 'hasPrice', 'categoryTermId', 'variantScope', 'visibleColumns'], true)) {
            $this->refreshGrid();
        }
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
        return $this->columnCatalogue() + ['price' => __('pim.field.price')];
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
     * @return array<int, array{product_id: int, name: string, sku: string, status: string, price: string|null}>
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
            ->map(fn (Product $product): array => [
                'product_id' => $product->id,
                'name' => $this->rowLabel($product),
                'sku' => $product->sku,
                'status' => $product->status,
                'price' => $product->prices->first()?->price,
            ])
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
     * @return Builder<Product>
     */
    protected function baseQuery(): Builder
    {
        $baseLanguageId = Locales::idFor(Locales::baseCode());

        return Product::query()
            // Priceable rows only: variable containers carry no price of their own.
            ->where('type', '!=', ProductType::Variable->value)
            ->when($this->variantScope === 'variants', fn (Builder $q) => $q->where('type', ProductType::Variant->value))
            ->when($this->variantScope === 'simple', fn (Builder $q) => $q->where('type', ProductType::Simple->value))
            ->when($this->search !== '', fn (Builder $q) => $q->where(fn (Builder $w) => $w
                ->where('sku', 'like', "%{$this->search}%")
                ->orWhereHas('translations', fn (Builder $t) => $t
                    ->where('language_id', $baseLanguageId)
                    ->where('name', 'like', "%{$this->search}%"))
                ->orWhereHas('parent.translations', fn (Builder $t) => $t
                    ->where('language_id', $baseLanguageId)
                    ->where('name', 'like', "%{$this->search}%"))))
            ->when($this->hasPrice === 'yes', fn (Builder $q) => $q->whereHas(
                'prices', fn (Builder $r) => $r->where('price_list_id', $this->priceListId),
            ))
            ->when($this->hasPrice === 'no', fn (Builder $q) => $q->whereDoesntHave(
                'prices', fn (Builder $r) => $r->where('price_list_id', $this->priceListId),
            ))
            ->when($this->categoryTermId, function (Builder $q): void {
                $term = TaxonomyTerm::query()->find($this->categoryTermId);
                $ids = $term ? [$term->getKey(), ...$term->descendantIds()] : [$this->categoryTermId];
                $q->whereHas('taxonomyTerms', fn (Builder $t) => $t->whereIn('taxonomy_terms.id', $ids));
            })
            // Keep a variant family together, then order by SKU.
            ->orderByRaw('COALESCE(parent_id, id)')
            ->orderByRaw('parent_id IS NOT NULL')
            ->orderBy('sku');
    }

    // ---- persistence from the grid ----------------------------------------

    /**
     * @param  array<int, array{product_id: int, price: mixed}>  $changes
     */
    public function saveCells(array $changes): void
    {
        DB::transaction(function () use ($changes): void {
            foreach ($changes as $change) {
                $this->writePrice((int) $change['product_id'], $change['price'] ?? null);
            }
        });
    }

    public function writePrice(int $productId, mixed $state): void
    {
        $state = ($state === '' || $state === null) ? null : round((float) $state, 2);

        if ($state === null) {
            ProductPrice::query()
                ->where('product_id', $productId)
                ->where('price_list_id', $this->priceListId)
                ->delete();

            return;
        }

        ProductPrice::updateOrCreate(
            ['product_id' => $productId, 'price_list_id' => $this->priceListId],
            ['price' => $state],
        );
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
