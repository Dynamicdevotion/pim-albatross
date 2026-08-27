<?php

namespace Modules\Localization\Filament\Resources\Languages\Tables;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Radio;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Modules\Localization\Models\Language;
use Modules\Localization\Support\LanguageContent;

class LanguagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('code')
                    ->label(__('pim.field.code'))
                    ->badge()
                    ->sortable(),
                TextColumn::make('name')
                    ->label(__('pim.field.name'))
                    ->searchable()
                    ->sortable(),
                IconColumn::make('is_base')
                    ->label(__('pim.field.base'))
                    ->boolean(),
                ToggleColumn::make('active')
                    ->label(__('pim.field.active'))
                    // The base language is always on; a language that already
                    // has translated content must be switched off through the
                    // "Deactivate…" action so the keep/delete choice is made.
                    ->disabled(fn (Language $record): bool => $record->is_base
                        || ($record->active && LanguageContent::has($record)))
                    ->afterStateUpdated(fn (Language $record, bool $state) => Notification::make()
                        ->title(__(
                            $state ? 'pim.notification.language_activated' : 'pim.notification.language_deactivated',
                            ['name' => $record->name],
                        ))
                        ->success()
                        ->send()),
            ])
            ->recordActions([
                Action::make('deactivateWithContent')
                    ->label(__('pim.action.deactivate'))
                    ->icon('heroicon-o-eye-slash')
                    ->color('warning')
                    ->visible(fn (Language $record): bool => $record->active
                        && ! $record->is_base
                        && LanguageContent::has($record))
                    ->modalHeading(fn (Language $record): string => __('pim.modal.deactivate_heading', ['name' => $record->name]))
                    ->modalDescription(__('pim.modal.deactivate_hint'))
                    ->form([
                        Radio::make('mode')
                            ->hiddenLabel()
                            ->required()
                            ->default('keep')
                            ->options([
                                'keep' => __('pim.modal.deactivate_mode.keep'),
                                'delete' => __('pim.modal.deactivate_mode.delete'),
                            ]),
                    ])
                    ->action(function (Language $record, array $data): void {
                        $removed = $data['mode'] === 'delete'
                            ? LanguageContent::purge($record)
                            : 0;

                        $record->update(['active' => false]);

                        Notification::make()
                            ->title(__('pim.notification.language_deactivated', ['name' => $record->name]))
                            ->body($data['mode'] === 'delete'
                                ? __('pim.notification.content_deleted', ['count' => $removed])
                                : __('pim.notification.content_kept'))
                            ->success()
                            ->send();
                    }),
                EditAction::make(),
                DeleteAction::make()
                    ->visible(fn (Language $record): bool => ! $record->is_base
                        && ! LanguageContent::has($record)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
