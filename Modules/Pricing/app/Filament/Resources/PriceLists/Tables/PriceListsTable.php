<?php

namespace Modules\Pricing\Filament\Resources\PriceLists\Tables;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Pricing\Models\PriceList;

class PriceListsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withCount('prices'))
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                IconColumn::make('is_default')
                    ->label('Default')
                    ->boolean(),
                ToggleColumn::make('active')
                    ->disabled(fn (PriceList $record): bool => $record->is_default),
                TextColumn::make('prices_count')
                    ->label('Prices')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                Action::make('setDefault')
                    ->label('Set as default')
                    ->icon('heroicon-o-star')
                    ->color('warning')
                    ->visible(fn (PriceList $record): bool => ! $record->is_default)
                    ->requiresConfirmation()
                    ->modalDescription('This list becomes the default and is forced active; the current default is demoted.')
                    ->action(function (PriceList $record): void {
                        $record->forceFill(['is_default' => true, 'active' => true])->save();

                        Notification::make()
                            ->title($record->name.' is now the default price list')
                            ->success()
                            ->send();
                    }),
                EditAction::make(),
                DeleteAction::make()
                    ->visible(fn (PriceList $record): bool => ! $record->is_default),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
