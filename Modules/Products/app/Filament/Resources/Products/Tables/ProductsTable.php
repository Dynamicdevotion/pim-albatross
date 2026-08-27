<?php

namespace Modules\Products\Filament\Resources\Products\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Localization\Enums\Locale;
use Modules\Products\Models\Product;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('translations'))
            ->columns([
                TextColumn::make('name_base')
                    ->label('Name')
                    ->getStateUsing(fn (Product $record): ?string => $record->translate(Locale::default())?->name)
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas(
                        'translations',
                        fn ($q) => $q->where('locale', Locale::default()->value)
                            ->where('name', 'like', "%{$search}%"),
                    )),
                TextColumn::make('translated_locales')
                    ->label('Translations')
                    ->badge()
                    ->getStateUsing(function (Product $record): array {
                        $order = Locale::values();

                        return $record->translations
                            ->pluck('locale')
                            ->unique()
                            ->sortBy(fn (string $locale): int => (int) array_search($locale, $order, true))
                            ->map(fn (string $locale): string => strtoupper($locale))
                            ->values()
                            ->all();
                    })
                    ->color(fn (string $state): string => $state === strtoupper(Locale::default()->value) ? 'primary' : 'gray')
                    ->placeholder('—')
                    ->tooltip('Locales this product has content for'),
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
                    ->options(
                        collect(Locale::cases())
                            ->mapWithKeys(fn (Locale $locale): array => [$locale->value => $locale->label()])
                            ->all(),
                    )
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->whereDoesntHave(
                            'translations',
                            fn (Builder $relation): Builder => $relation->where('locale', $data['value']),
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
