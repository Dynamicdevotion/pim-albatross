<?php

namespace Modules\Products\Filament\Resources\Products\Tables;

use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Modules\Localization\Models\Language;
use Modules\Localization\Support\Locales;
use Modules\Products\Models\Product;
use Modules\Taxonomies\Models\Taxonomy;
use Modules\Taxonomies\Models\TaxonomyTerm;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with([
                'translations',
                'taxonomyTerms.taxonomy',
            ]))
            ->columns([
                TextColumn::make('name_base')
                    ->label(__('pim.field.name'))
                    ->getStateUsing(fn (Product $record): ?string => $record->translate(Locales::baseCode())?->name)
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas(
                        'translations',
                        fn (Builder $q) => $q->where('language_id', Locales::idFor(Locales::baseCode()))
                            ->where('name', 'like', "%{$search}%"),
                    )),
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
            ->filters([
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
