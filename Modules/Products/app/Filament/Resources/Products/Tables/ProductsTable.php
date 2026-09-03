<?php

namespace Modules\Products\Filament\Resources\Products\Tables;

use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Modules\Localization\Models\Language;
use Modules\Localization\Support\Locales;
use Modules\Pricing\Models\PriceList;
use Modules\Products\Enums\ProductType;
use Modules\Products\Models\Product;
use Modules\Products\Support\ProductListQuery;
use Modules\Products\Support\ProductRowActions;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // Top-level products only + the list's eager loads. Shared with the
            // export so the two never diverge — see ProductListQuery.
            ->modifyQueryUsing(fn (Builder $query): Builder => ProductListQuery::applyBase($query))
            // The generic toolbar search box is gone; name/SKU search lives in
            // the filter drawer as the `search` filter below.
            ->searchable(false)
            // Every column is freely toggleable from the column manager — no
            // column is locked visible.
            ->columns([
                ImageColumn::make('main_image')
                    ->label(__('pim.field.image'))
                    ->getStateUsing(fn (Product $record): ?string => $record->getMainImageUrl('thumb'))
                    ->imageSize(40)
                    ->square()
                    ->defaultImageUrl(fn (): string => asset('images/placeholder-product.svg'))
                    ->toggleable(),
                TextColumn::make('name_base')
                    ->label(__('pim.field.name'))
                    ->getStateUsing(fn (Product $record): ?string => $record->translate(Locales::baseCode())?->name)
                    ->toggleable(),
                TextColumn::make('type')
                    ->label(__('pim.field.type'))
                    ->badge()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('variants_count')
                    ->label(__('pim.field.variants'))
                    ->tooltip(__('pim.tooltip.variants_count'))
                    ->formatStateUsing(fn (int $state, Product $record): string => $record->isVariable()
                        ? trans_choice('pim.column.variants_count', $state, ['count' => $state])
                        : '—')
                    ->toggleable(),
                TextColumn::make('translated_locales')
                    ->label(__('pim.field.translations'))
                    ->badge()
                    ->getStateUsing(function (Product $record): array {
                        $order = Locales::activeCodes();

                        return $record->translations
                            ->map(fn ($translation): ?string => Locales::codeFor((int) $translation->language_id))
                            ->filter()
                            ->unique()
                            ->sortBy(fn (string $code): int => (int) array_search($code, $order, true))
                            ->map(fn (string $code): string => strtoupper($code))
                            ->values()
                            ->all();
                    })
                    ->color(fn (string $state): string => $state === strtoupper(Locales::baseCode()) ? 'primary' : 'gray')
                    ->placeholder('—')
                    ->tooltip(__('pim.tooltip.translated_languages'))
                    ->toggleable(),
                TextColumn::make('taxonomy_terms')
                    ->label(__('pim.field.terms'))
                    ->badge()
                    ->getStateUsing(fn (Product $record): array => $record->taxonomyTerms
                        ->sortBy(fn ($term): string => $term->taxonomy->name.$term->name)
                        ->map(fn ($term): string => "{$term->taxonomy->name}: {$term->name}")
                        ->values()
                        ->all())
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('sku')
                    ->label(__('pim.field.sku'))
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('external_id')
                    ->label(__('pim.field.external_id'))
                    ->toggleable(),
                TextColumn::make('stock')
                    ->label(__('pim.field.stock'))
                    ->numeric()
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('weight')
                    ->label(__('pim.field.weight'))
                    ->numeric()
                    ->suffix(' kg')
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('length')
                    ->label(__('pim.field.length'))
                    ->numeric()
                    ->suffix(' cm')
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('width')
                    ->label(__('pim.field.width'))
                    ->numeric()
                    ->suffix(' cm')
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('height')
                    ->label(__('pim.field.height'))
                    ->numeric()
                    ->suffix(' cm')
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')
                    ->label(__('pim.field.status'))
                    ->badge()
                    ->sortable()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'archived' => 'danger',
                        default => 'gray',
                    })
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            // Rendered by our own bottom drawer in the page view; Filament must
            // not also render a trigger/panel of its own.
            ->filtersLayout(FiltersLayout::Hidden)
            ->filtersFormColumns(2)
            // Every filter's query lives in ProductListQuery so the export (and
            // the bulk price grid) can replay the exact same clauses from a
            // saved filter snapshot.
            ->filters(self::filters())
            ->recordActions([
                EditAction::make(),
                // Extra per-row actions contributed by other modules (e.g.
                // WooSync's "Sincronizza con WooCommerce"), if any.
                ...ProductRowActions::record(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('assignTaxonomyTerms')
                        ->label(__('pim.action.assign_taxonomy_terms'))
                        ->icon('heroicon-o-tag')
                        ->schema([
                            Select::make('terms')
                                ->label(__('pim.field.terms'))
                                ->multiple()
                                ->searchable()
                                ->required()
                                ->options(fn (): array => self::taxonomyTermOptions()),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            $records->each(fn (Product $product) => $product->taxonomyTerms()
                                ->syncWithoutDetaching($data['terms']));

                            Notification::make()
                                ->title(__('pim.notification.terms_assigned', ['count' => $records->count()]))
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    // Extra bulk actions contributed by other modules
                    // (e.g. WooSync), if any.
                    ...ProductRowActions::bulk(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * The full filter set behind the drawer, in display order. Public so other
     * screens over the same product query (the bulk price grid) can reuse the
     * identical set, or compose their own subset plus a screen-specific filter
     * — see {@see \Modules\Pricing\Filament\Pages\ManagePrices}.
     *
     * @return list<Filter>
     */
    public static function filters(): array
    {
        return [
            self::searchFilter(),
            self::typeFilter(),
            self::statusFilter(),
            self::missingTranslationFilter(),
            self::taxonomyFilter(),
            self::priceFilter(),
            self::stockFilter(),
        ];
    }

    public static function typeFilter(): SelectFilter
    {
        return SelectFilter::make('type')
            ->label(__('pim.filter.type'))
            ->options(collect(ProductType::cases())
                ->mapWithKeys(fn (ProductType $type): array => [$type->value => $type->getLabel()])
                ->all())
            ->query(fn (Builder $query, array $data): Builder => ProductListQuery::type($query, $data));
    }

    public static function statusFilter(): SelectFilter
    {
        return SelectFilter::make('status')
            ->label(__('pim.field.status'))
            ->options([
                'draft' => __('pim.option.status.draft'),
                'active' => __('pim.option.status.active'),
                'archived' => __('pim.option.status.archived'),
            ])
            ->query(fn (Builder $query, array $data): Builder => ProductListQuery::status($query, $data));
    }

    public static function missingTranslationFilter(): SelectFilter
    {
        return SelectFilter::make('missing_translation')
            ->label(__('pim.filter.missing_translation'))
            ->options(fn (): array => [
                '*' => __('pim.filter.missing_translation_any'),
                ...Locales::active()
                    ->mapWithKeys(fn (Language $language): array => [$language->code => $language->name])
                    ->all(),
            ])
            ->query(fn (Builder $query, array $data): Builder => ProductListQuery::missingTranslation($query, $data));
    }

    /**
     * Free-text search on the base-language name or the SKU. Replaces the
     * generic toolbar search box, deferred like every other filter.
     */
    public static function searchFilter(): Filter
    {
        return Filter::make('search')
            ->label(__('pim.field.search'))
            ->schema([
                TextInput::make('term')
                    ->label(__('pim.field.search'))
                    ->placeholder(__('pim.grid.search_placeholder'))
                    ->columnSpanFull(),
            ])
            ->query(fn (Builder $query, array $data): Builder => ProductListQuery::search($query, $data))
            ->indicateUsing(function (array $data): ?string {
                $term = trim((string) ($data['term'] ?? ''));

                return $term === '' ? null : __('pim.field.search').': '.$term;
            });
    }

    /**
     * Faceted taxonomy filter: AND across taxonomies, OR within one, each
     * selected term expanded to its subtree.
     */
    public static function taxonomyFilter(): Filter
    {
        return Filter::make('taxonomy_terms')
            ->label(__('pim.filter.taxonomy_terms'))
            ->schema([
                Select::make('terms')
                    ->label(__('pim.field.taxonomy_terms'))
                    ->multiple()
                    ->searchable()
                    ->options(fn (): array => self::taxonomyTermOptions()),
            ])
            ->query(fn (Builder $query, array $data): Builder => ProductListQuery::taxonomyTerms($query, $data))
            ->indicateUsing(function (array $data): ?string {
                $ids = array_filter($data['terms'] ?? []);

                if ($ids === []) {
                    return null;
                }

                return __('pim.filter.taxonomy_terms').': '.collect(self::taxonomyTermOptions())
                    ->only($ids)
                    ->values()
                    ->implode(', ');
            });
    }

    /**
     * Price presence + range, on one price list (default: the default list).
     */
    public static function priceFilter(): Filter
    {
        return Filter::make('price')
            ->label(__('pim.filter.price'))
            ->columns(2)
            ->schema([
                Select::make('price_list_id')
                    ->label(__('pim.field.price_list'))
                    ->native(false)
                    ->selectablePlaceholder(false)
                    ->options(fn (): array => PriceList::query()->active()
                        ->orderByDesc('is_default')
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->default(fn (): ?int => PriceList::query()->where('is_default', true)->value('id')),
                Select::make('presence')
                    ->label(__('pim.filter.price_presence'))
                    ->native(false)
                    ->options([
                        'with' => __('pim.option.price.with'),
                        'without' => __('pim.option.price.without'),
                    ]),
                TextInput::make('min')
                    ->label(__('pim.field.price_min'))
                    ->numeric()
                    ->minValue(0),
                TextInput::make('max')
                    ->label(__('pim.field.price_max'))
                    ->numeric()
                    ->minValue(0),
            ])
            ->query(fn (Builder $query, array $data): Builder => ProductListQuery::price($query, $data))
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

                if ($parts === []) {
                    return null;
                }

                $listName = PriceList::query()->find($data['price_list_id'] ?? null)?->name;

                return __('pim.filter.price').': '.implode(', ', $parts).($listName ? " ({$listName})" : '');
            });
    }

    /**
     * Stock level. `whereNotNull('stock')` keeps variable containers out.
     */
    public static function stockFilter(): Filter
    {
        $threshold = (int) config('products.low_stock_threshold', 5);

        return Filter::make('stock')
            ->label(__('pim.field.stock'))
            ->schema([
                Select::make('level')
                    ->label(__('pim.field.stock'))
                    ->native(false)
                    ->options([
                        'zero' => __('pim.option.stock.zero'),
                        'low' => __('pim.option.stock.low', ['threshold' => $threshold]),
                    ]),
            ])
            ->query(fn (Builder $query, array $data): Builder => ProductListQuery::stock($query, $data))
            ->indicateUsing(fn (array $data): ?string => match ($data['level'] ?? null) {
                'zero' => __('pim.field.stock').': '.__('pim.option.stock.zero'),
                'low' => __('pim.field.stock').': '.__('pim.option.stock.low', ['threshold' => $threshold]),
                default => null,
            });
    }

    /**
     * Term id => "Taxonomy: Term", grouped by taxonomy in a stable order.
     * Kept here for the bulk "assign taxonomy terms" action; the canonical
     * implementation lives in ProductListQuery.
     *
     * @return array<int, string>
     */
    public static function taxonomyTermOptions(): array
    {
        return ProductListQuery::taxonomyTermOptions();
    }
}
