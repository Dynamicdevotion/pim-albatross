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
use Modules\Taxonomies\Models\Taxonomy;
use Modules\Taxonomies\Models\TaxonomyTerm;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // Top-level products only: variants are managed inside their parent.
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->whereNull('parent_id')
                ->withCount('variants')
                ->with(['translations', 'taxonomyTerms.taxonomy', 'media']))
            ->columns([
                ImageColumn::make('main_image')
                    ->label(__('pim.field.image'))
                    ->getStateUsing(fn (Product $record): ?string => $record->getMainImageUrl('thumb'))
                    ->imageHeight(40)
                    ->defaultImageUrl(fn (): string => asset('images/placeholder-product.svg')),
                TextColumn::make('name_base')
                    ->label(__('pim.field.name'))
                    ->getStateUsing(fn (Product $record): ?string => $record->translate(Locales::baseCode())?->name)
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas(
                        'translations',
                        fn (Builder $q) => $q->where('language_id', Locales::idFor(Locales::baseCode()))
                            ->where('name', 'like', "%{$search}%"),
                    )),
                TextColumn::make('type')
                    ->label(__('pim.field.type'))
                    ->badge()
                    ->sortable(),
                TextColumn::make('variants_count')
                    ->label(__('pim.field.variants'))
                    ->tooltip(__('pim.tooltip.variants_count'))
                    ->formatStateUsing(fn (int $state, Product $record): string => $record->isVariable()
                        ? trans_choice('pim.column.variants_count', $state, ['count' => $state])
                        : '—'),
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
                    ->tooltip(__('pim.tooltip.translated_languages')),
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
                    ->searchable()
                    ->sortable(),
                TextColumn::make('external_id')
                    ->label(__('pim.field.external_id'))
                    ->searchable()
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
                    }),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filtersFormColumns(2)
            ->filters([
                SelectFilter::make('type')
                    ->label(__('pim.filter.type'))
                    ->options(collect(ProductType::cases())
                        ->mapWithKeys(fn (ProductType $type): array => [$type->value => $type->getLabel()])
                        ->all()),
                SelectFilter::make('status')
                    ->label(__('pim.field.status'))
                    ->options([
                        'draft' => __('pim.option.status.draft'),
                        'active' => __('pim.option.status.active'),
                        'archived' => __('pim.option.status.archived'),
                    ]),
                SelectFilter::make('missing_translation')
                    ->label(__('pim.filter.missing_translation'))
                    ->options(fn (): array => Locales::active()
                        ->mapWithKeys(fn (Language $language): array => [$language->code => $language->name])
                        ->all())
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->whereDoesntHave(
                            'translations',
                            fn (Builder $relation): Builder => $relation->where('language_id', Locales::idFor($data['value'])),
                        )
                        : $query),
                self::taxonomyFilter(),
                self::priceFilter(),
                self::stockFilter(),
            ])
            ->recordActions([
                EditAction::make(),
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
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Faceted taxonomy filter: AND across taxonomies, OR within one, each
     * selected term expanded to its subtree.
     */
    protected static function taxonomyFilter(): Filter
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
            ->query(function (Builder $query, array $data): Builder {
                $ids = array_values(array_filter(array_map('intval', $data['terms'] ?? [])));

                if ($ids === []) {
                    return $query;
                }

                $byTaxonomy = [];

                foreach (TaxonomyTerm::query()->whereIn('id', $ids)->get() as $term) {
                    $subtree = [$term->getKey(), ...$term->descendantIds()];
                    $byTaxonomy[$term->taxonomy_id] = array_merge($byTaxonomy[$term->taxonomy_id] ?? [], $subtree);
                }

                foreach ($byTaxonomy as $termIds) {
                    $query->whereHas(
                        'taxonomyTerms',
                        fn (Builder $relation): Builder => $relation
                            ->whereIn('taxonomy_terms.id', array_values(array_unique($termIds))),
                    );
                }

                return $query;
            })
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
    protected static function priceFilter(): Filter
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
            ->query(function (Builder $query, array $data): Builder {
                $listId = (int) ($data['price_list_id'] ?? 0)
                    ?: (int) (PriceList::query()->where('is_default', true)->value('id') ?? 0);

                if ($listId === 0) {
                    return $query;
                }

                $presence = $data['presence'] ?? null;
                $min = filled($data['min'] ?? null) ? (float) $data['min'] : null;
                $max = filled($data['max'] ?? null) ? (float) $data['max'] : null;

                if ($presence === 'without') {
                    return $query->whereDoesntHave(
                        'prices',
                        fn (Builder $relation): Builder => $relation->where('price_list_id', $listId),
                    );
                }

                if ($presence === 'with' || $min !== null || $max !== null) {
                    return $query->whereHas('prices', fn (Builder $relation): Builder => $relation
                        ->where('price_list_id', $listId)
                        ->when($min !== null, fn (Builder $q): Builder => $q->where('price', '>=', $min))
                        ->when($max !== null, fn (Builder $q): Builder => $q->where('price', '<=', $max)));
                }

                return $query;
            })
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
    protected static function stockFilter(): Filter
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
            ->query(fn (Builder $query, array $data): Builder => match ($data['level'] ?? null) {
                'zero' => $query->whereNotNull('stock')->where('stock', 0),
                'low' => $query->whereNotNull('stock')->whereBetween('stock', [1, $threshold]),
                default => $query,
            })
            ->indicateUsing(fn (array $data): ?string => match ($data['level'] ?? null) {
                'zero' => __('pim.field.stock').': '.__('pim.option.stock.zero'),
                'low' => __('pim.field.stock').': '.__('pim.option.stock.low', ['threshold' => $threshold]),
                default => null,
            });
    }

    /**
     * Term id => "Taxonomy: Term", grouped by taxonomy in a stable order.
     *
     * @return array<int, string>
     */
    public static function taxonomyTermOptions(): array
    {
        $options = [];

        foreach (Taxonomy::query()->with(['translations', 'terms.translations'])->get() as $taxonomy) {
            foreach ($taxonomy->terms as $term) {
                $options[$term->id] = "{$taxonomy->name}: {$term->name}";
            }
        }

        return $options;
    }
}
