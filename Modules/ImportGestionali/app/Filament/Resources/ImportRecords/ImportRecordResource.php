<?php

namespace Modules\ImportGestionali\Filament\Resources\ImportRecords;

use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Modules\ImportGestionali\Filament\Resources\ImportRecords\Pages\ListImportRecords;
use Modules\ImportGestionali\Filament\Resources\ImportRecords\Pages\ViewImportRecord;
use Modules\ImportGestionali\Models\ImportRecord;

class ImportRecordResource extends Resource
{
    protected static ?string $model = ImportRecord::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'esiti-import';

    public static function getNavigationGroup(): ?string
    {
        return __('pim.import.nav.group');
    }

    public static function getNavigationLabel(): string
    {
        return __('pim.import.nav.history');
    }

    public static function getModelLabel(): string
    {
        return __('pim.import.record.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('pim.import.record.plural');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('pim.import.col.when'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('original_filename')
                    ->label(__('pim.import.field.file'))
                    ->limit(40),
                TextColumn::make('user.name')
                    ->label(__('pim.import.col.user'))
                    ->placeholder('—'),
                TextColumn::make('status')
                    ->label(__('pim.field.status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => __('pim.import.status.'.$state))
                    ->color(fn (string $state): string => match ($state) {
                        'completed' => 'success',
                        'failed' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('created_count')->label(__('pim.import.col.created'))->badge()->color('success'),
                TextColumn::make('updated_count')->label(__('pim.import.col.updated'))->badge()->color('warning'),
                TextColumn::make('skipped_count')->label(__('pim.import.col.skipped'))->badge()->color('gray'),
            ])
            ->recordActions([
                ViewAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('pim.import.report.summary'))
                ->columns(3)
                ->schema([
                    TextEntry::make('original_filename')->label(__('pim.import.field.file')),
                    TextEntry::make('user.name')->label(__('pim.import.col.user'))->placeholder('—'),
                    TextEntry::make('status')
                        ->label(__('pim.field.status'))
                        ->badge()
                        ->formatStateUsing(fn (string $state): string => __('pim.import.status.'.$state))
                        ->color(fn (string $state): string => match ($state) {
                            'completed' => 'success',
                            'failed' => 'danger',
                            default => 'gray',
                        }),
                    TextEntry::make('started_at')->label(__('pim.import.report.started'))->dateTime('d/m/Y H:i:s')->placeholder('—'),
                    TextEntry::make('finished_at')->label(__('pim.import.report.finished'))->dateTime('d/m/Y H:i:s')->placeholder('—'),
                    TextEntry::make('total_rows')->label(__('pim.import.report.total_rows'))->placeholder('—'),
                ]),

            TextEntry::make('running_hint')
                ->hiddenLabel()
                ->state(fn (): string => __('pim.import.report.running_hint'))
                ->color('gray')
                ->visible(fn (ImportRecord $record): bool => $record->isRunning()),

            TextEntry::make('error_message')
                ->label(__('pim.import.report.error'))
                ->color('danger')
                ->visible(fn (ImportRecord $record): bool => filled($record->error_message)),

            Section::make(__('pim.import.report.counts'))
                ->columns(3)
                ->schema([
                    TextEntry::make('created_count')->label(__('pim.import.col.created'))->badge()->color('success'),
                    TextEntry::make('updated_count')->label(__('pim.import.col.updated'))->badge()->color('warning'),
                    TextEntry::make('skipped_count')->label(__('pim.import.col.skipped'))->badge()->color('gray'),
                ]),

            Section::make(__('pim.import.report.skipped_rows'))
                ->visible(fn (ImportRecord $record): bool => filled($record->issues))
                ->schema([
                    RepeatableEntry::make('issues')
                        ->hiddenLabel()
                        ->schema([
                            TextEntry::make('reason')->hiddenLabel(),
                        ]),
                    TextEntry::make('issues_more')
                        ->hiddenLabel()
                        ->color('gray')
                        ->state(fn (ImportRecord $record): ?string => $record->skipped_count > count($record->issues ?? [])
                            ? __('pim.import.report.more', ['count' => $record->skipped_count - count($record->issues ?? [])])
                            : null)
                        ->visible(fn (ImportRecord $record): bool => $record->skipped_count > count($record->issues ?? [])),
                ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListImportRecords::route('/'),
            'view' => ViewImportRecord::route('/{record}'),
        ];
    }
}
