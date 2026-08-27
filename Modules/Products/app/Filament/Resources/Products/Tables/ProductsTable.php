<?php

namespace Modules\Products\Filament\Resources\Products\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Localization\Models\Language;
use Modules\Localization\Support\Locales;
use Modules\Products\Models\Product;

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
                    ->label('Name')
                    ->getStateUsing(fn (Product $record): ?string => $record->translate(Locales::baseCode())?->name)
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas(
                        'translations',
                        fn (Builder $q) => $q->where('language_id', Locales::idFor(Locales::baseCode()))
                            ->where('name', 'like', "%{$search}%"),
                    )),
                TextColumn::make('translated_locales')
                    ->label('Translations')
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
                    ->tooltip('Languages this product has content for'),
                TextColumn::make('taxonomy_terms')
                    ->label('Terms')
                    ->badge()
                    ->getStateUsing(fn (Product $record): array => $record->taxonomyTerms
                        ->sortBy(fn ($term): string => $term->taxonomy->name.$term->name)
                        ->map(fn ($term): string => "{$term->taxonomy->name}: {$term->name}")
                        ->values()
                        ->all())
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('external_id')
                    ->label('External ID')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('status')
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
                    ->options([
                        'draft' => 'Draft',
                        'active' => 'Active',
                        'archived' => 'Archived',
                    ]),
                SelectFilter::make('missing_translation')
                    ->label('Missing translation')
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
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
