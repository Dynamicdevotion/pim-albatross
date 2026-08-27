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
                    ->badge()
                    ->sortable(),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                IconColumn::make('is_base')
                    ->label('Base')
                    ->boolean(),
                ToggleColumn::make('active')
                    // The base language is always on; a language that already
                    // has translated content must be switched off through the
                    // "Deactivate…" action so the keep/delete choice is made.
                    ->disabled(fn (Language $record): bool => $record->is_base
                        || ($record->active && LanguageContent::has($record)))
                    ->afterStateUpdated(fn (Language $record, bool $state) => Notification::make()
                        ->title($record->name.' '.($state ? 'activated' : 'deactivated'))
                        ->success()
                        ->send()),
            ])
            ->recordActions([
                Action::make('deactivateWithContent')
                    ->label('Deactivate…')
                    ->icon('heroicon-o-eye-slash')
                    ->color('warning')
                    ->visible(fn (Language $record): bool => $record->active
                        && ! $record->is_base
                        && LanguageContent::has($record))
                    ->modalHeading(fn (Language $record): string => 'Deactivate '.$record->name)
                    ->modalDescription('This language already has translated content in the catalogue.')
                    ->form([
                        Radio::make('mode')
                            ->hiddenLabel()
                            ->required()
                            ->default('keep')
                            ->options([
                                'keep' => 'Keep the content — just hide it (it reappears if the language is re-activated)',
                                'delete' => 'Delete every translation in this language',
                            ]),
                    ])
                    ->action(function (Language $record, array $data): void {
                        $removed = $data['mode'] === 'delete'
                            ? LanguageContent::purge($record)
                            : 0;

                        $record->update(['active' => false]);

                        Notification::make()
                            ->title($record->name.' deactivated')
                            ->body($data['mode'] === 'delete'
                                ? $removed.' translation row(s) removed.'
                                : 'Content kept and hidden.')
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
