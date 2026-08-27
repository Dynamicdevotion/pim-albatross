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
                    ->label(__('pim.field.name'))
                    ->searchable()
                    ->sortable(),
                IconColumn::make('is_default')
                    ->label(__('pim.field.default'))
                    ->boolean(),
                ToggleColumn::make('active')
                    ->label(__('pim.field.active'))
                    ->disabled(fn (PriceList $record): bool => $record->is_default),
                TextColumn::make('prices_count')
                    ->label(__('pim.field.prices'))
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                Action::make('setDefault')
                    ->label(__('pim.action.set_default'))
                    ->icon('heroicon-o-star')
                    ->color('warning')
                    ->visible(fn (PriceList $record): bool => ! $record->is_default)
                    ->requiresConfirmation()
                    ->modalDescription(__('pim.modal.set_default_hint'))
                    ->action(function (PriceList $record): void {
                        $record->forceFill(['is_default' => true, 'active' => true])->save();

                        Notification::make()
                            ->title(__('pim.notification.price_list_default', ['name' => $record->name]))
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
