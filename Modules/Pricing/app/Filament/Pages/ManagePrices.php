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
use Modules\Products\Models\Product;
use Modules\SavedViews\Filament\Concerns\InteractsWithSavedViews;
use Modules\Taxonomies\Models\Taxonomy;
use Modules\Taxonomies\Models\TaxonomyTerm;

/**
 * Excel-like editor for every product's price in one price list. A jspreadsheet
 * grid (drag select, paste from Excel, drag-fill) sits under a toolbar of
 * filters, per-user saved views, column toggles and bulk % actions.
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

    /** @var list<string> */
    public array $visibleColumns = ['name', 'sku', 'status'];

    /** @var list<int> */
    public array $selectedProductIds = [];

    public static function canAccess(): bool
    {
        return PriceList::query()->exists();
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
        $this->visibleColumns = $state['columns'] ?? ['name', 'sku', 'status'];

        $this->refreshGrid();
    }

    // ---- reactive toolbar ---------------------------------------------------

    public function updated(string $property): void
    {
        if (in_array($property, ['priceListId', 'search', 'hasPrice', 'categoryTermId', 'visibleColumns'], true)) {
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
        return ['name' => 'Name', 'sku' => 'SKU', 'status' => 'Status'];
    }

    /**
     * @return array<string, string>
     */
    public function gridHeaders(): array
    {
        return $this->columnCatalogue() + ['price' => 'Price'];
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
     * @return array<int, array{product_id: int, name: string|null, sku: string, status: string, price: string|null}>
     */
    public function rows(): array
    {
        return $this->baseQuery()
            ->with([
                'translations',
                'prices' => fn ($query) => $query->where('price_list_id', $this->priceListId),
            ])
            ->limit(self::ROW_CAP)
            ->get()
            ->map(fn (Product $product): array => [
                'product_id' => $product->id,
                'name' => $product->translate(Locales::baseCode())?->name,
                'sku' => $product->sku,
                'status' => $product->status,
                'price' => $product->prices->first()?->price,
            ])
            ->all();
    }

    /**
     * @return Builder<Product>
     */
    protected function baseQuery(): Builder
    {
        $baseLanguageId = Locales::idFor(Locales::baseCode());

        return Product::query()
            ->when($this->search !== '', fn (Builder $q) => $q->where(fn (Builder $w) => $w
                ->where('sku', 'like', "%{$this->search}%")
                ->orWhereHas('translations', fn (Builder $t) => $t
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
            Notification::make()->title('Select some rows in the grid first')->warning()->send();

            return false;
        }

        return true;
    }

    public function setFixedPriceAction(): Action
    {
        return Action::make('setFixedPrice')
            ->label('Set price')
            ->icon('heroicon-o-currency-euro')
            ->schema([
                TextInput::make('price')->numeric()->minValue(0)->required()
                    ->extraInputAttributes(['step' => '0.01']),
            ])
            ->action(function (array $data): void {
                if (! $this->assertSelection()) {
                    return;
                }

                $n = PriceAdjuster::setFixed($this->selectedProductIds, $this->priceListId, (float) $data['price']);
                $this->notifyDone("$n price(s) set");
            });
    }

    public function adjustSelectionAction(): Action
    {
        return Action::make('adjustSelection')
            ->label('Adjust % (selection)')
            ->icon('heroicon-o-receipt-percent')
            ->schema([
                TextInput::make('percent')->numeric()->required()
                    ->helperText('e.g. 10 for +10%, -15 for −15%.'),
            ])
            ->action(function (array $data): void {
                if (! $this->assertSelection()) {
                    return;
                }

                $n = PriceAdjuster::adjustProducts($this->selectedProductIds, $this->priceListId, (float) $data['percent']);
                $this->notifyDone("$n price(s) adjusted (rows without a price in this list were skipped)");
            });
    }

    public function adjustCategoryAction(): Action
    {
        return Action::make('adjustCategory')
            ->label('Adjust % (category)')
            ->icon('heroicon-o-tag')
            ->schema([
                Select::make('taxonomy_term_id')
                    ->label('Category')
                    ->options(fn (): array => $this->categoryOptions())
                    ->searchable()
                    ->required(),
                TextInput::make('percent')->numeric()->required()
                    ->helperText('Applies only to the price list selected above.'),
            ])
            ->action(function (array $data): void {
                $n = PriceAdjuster::adjustCategory(
                    (int) $data['taxonomy_term_id'],
                    $this->priceListId,
                    (float) $data['percent'],
                );
                $this->notifyDone("$n price(s) adjusted in this list");
            });
    }

    protected function notifyDone(string $message): void
    {
        Notification::make()->title($message)->success()->send();
        $this->refreshGrid();
    }
}
