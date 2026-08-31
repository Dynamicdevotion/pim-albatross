<?php

namespace Modules\ExportProdotti\Filament\Resources\ExportRecords;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use Modules\ExportProdotti\Enums\ExportColumn;
use Modules\ExportProdotti\Filament\Resources\ExportRecords\Pages\ListExportRecords;
use Modules\ExportProdotti\Filament\Resources\ExportRecords\Pages\ViewExportRecord;
use Modules\ExportProdotti\Models\ExportRecord;

class ExportRecordResource extends Resource
{
    protected static ?string $model = ExportRecord::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowDownTray;

    protected static ?string $slug = 'esiti-export';

    public static function getNavigationGroup(): ?string
    {
        return __('pim.export.nav.group');
    }

    public static function getNavigationLabel(): string
    {
        return __('pim.export.nav.history');
    }

    public static function getModelLabel(): string
    {
        return __('pim.export.record.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('pim.export.record.plural');
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
                    ->label(__('pim.export.col.when'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label(__('pim.export.col.user'))
                    ->placeholder('—'),
                TextColumn::make('format')
                    ->label(__('pim.export.col.format'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => strtoupper($state)),
                TextColumn::make('columns_count')
                    ->label(__('pim.export.col.columns'))
                    ->badge()
                    ->state(fn (ExportRecord $record): int => count($record->columns ?? []))
                    ->tooltip(fn (ExportRecord $record): string => collect($record->columns ?? [])
                        ->map(fn (string $key): string => ExportColumn::tryFrom($key)?->label() ?? $key)
                        ->implode(', ')),
                TextColumn::make('status')
                    ->label(__('pim.field.status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => __('pim.export.status.'.$state))
                    ->color(fn (string $state): string => match ($state) {
                        'completed' => 'success',
                        'failed' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('row_count')
                    ->label(__('pim.export.col.rows'))
                    ->placeholder('—')
                    ->badge(),
            ])
            ->recordActions([
                ViewAction::make(),
                self::downloadAction(),
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
            Section::make(__('pim.export.report.summary'))
                ->columns(3)
                ->schema([
                    TextEntry::make('user.name')->label(__('pim.export.col.user'))->placeholder('—'),
                    TextEntry::make('format')
                        ->label(__('pim.export.col.format'))
                        ->badge()
                        ->formatStateUsing(fn (string $state): string => strtoupper($state)),
                    TextEntry::make('status')
                        ->label(__('pim.field.status'))
                        ->badge()
                        ->formatStateUsing(fn (string $state): string => __('pim.export.status.'.$state))
                        ->color(fn (string $state): string => match ($state) {
                            'completed' => 'success',
                            'failed' => 'danger',
                            default => 'gray',
                        }),
                    TextEntry::make('started_at')->label(__('pim.export.report.started'))->dateTime('d/m/Y H:i:s')->placeholder('—'),
                    TextEntry::make('finished_at')->label(__('pim.export.report.finished'))->dateTime('d/m/Y H:i:s')->placeholder('—'),
                    TextEntry::make('row_count')->label(__('pim.export.report.rows'))->placeholder('—'),
                ]),

            TextEntry::make('columns_list')
                ->label(__('pim.export.report.columns'))
                ->state(fn (ExportRecord $record): string => collect($record->columns ?? [])
                    ->map(fn (string $key): string => ExportColumn::tryFrom($key)?->label() ?? $key)
                    ->implode(', ')),

            TextEntry::make('running_hint')
                ->hiddenLabel()
                ->state(fn (): string => __('pim.export.report.running_hint'))
                ->color('gray')
                ->visible(fn (ExportRecord $record): bool => $record->isRunning()),

            TextEntry::make('error_message')
                ->label(__('pim.export.report.error'))
                ->color('danger')
                ->visible(fn (ExportRecord $record): bool => filled($record->error_message)),
        ]);
    }

    public static function downloadAction(): Action
    {
        return Action::make('download')
            ->label(__('pim.export.action.download'))
            ->icon('heroicon-o-arrow-down-tray')
            ->visible(fn (ExportRecord $record): bool => $record->isDownloadable())
            ->action(fn (ExportRecord $record) => Storage::disk(config('exportprodotti.disk'))
                ->download($record->stored_path, $record->original_filename));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListExportRecords::route('/'),
            'view' => ViewExportRecord::route('/{record}'),
        ];
    }
}
