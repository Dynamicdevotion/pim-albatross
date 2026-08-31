<?php

namespace Modules\WooSync\Filament\Resources\WooSyncRuns;

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
use Modules\WooSync\Filament\Actions\SyncProductsAction;
use Modules\WooSync\Filament\Resources\WooSyncRuns\Pages\ListWooSyncRuns;
use Modules\WooSync\Filament\Resources\WooSyncRuns\Pages\ViewWooSyncRun;
use Modules\WooSync\Models\WooSyncRun;

/**
 * The read-only history of "Sincronizza con WooCommerce" runs and their
 * per-product outcomes — the same report shape as ImportGestionali /
 * ExportProdotti. Runs are created by {@see SyncProductsAction},
 * never here.
 */
class WooSyncRunResource extends Resource
{
    protected static ?string $model = WooSyncRun::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowPathRoundedSquare;

    protected static ?string $slug = 'sincronizzazioni-woocommerce';

    protected static ?int $navigationSort = 91;

    public static function getNavigationGroup(): ?string
    {
        return __('pim.woosync.nav.group');
    }

    public static function getNavigationLabel(): string
    {
        return __('pim.woosync.nav.runs');
    }

    public static function getModelLabel(): string
    {
        return __('pim.woosync.run.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('pim.woosync.run.plural');
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
                    ->label(__('pim.woosync.col.when'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label(__('pim.woosync.col.user'))
                    ->placeholder('—'),
                TextColumn::make('trigger')
                    ->label(__('pim.woosync.col.trigger'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => __('pim.woosync.trigger.'.$state)),
                TextColumn::make('status')
                    ->label(__('pim.field.status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => __('pim.woosync.status.'.$state))
                    ->color(fn (string $state): string => match ($state) {
                        'completed' => 'success',
                        'failed' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('total')
                    ->label(__('pim.woosync.col.total'))
                    ->badge(),
                TextColumn::make('created_count')
                    ->label(__('pim.woosync.col.created'))
                    ->badge()
                    ->color('success'),
                TextColumn::make('updated_count')
                    ->label(__('pim.woosync.col.updated'))
                    ->badge()
                    ->color('info'),
                TextColumn::make('skipped_count')
                    ->label(__('pim.woosync.col.skipped'))
                    ->badge()
                    ->color('gray'),
                TextColumn::make('failed_count')
                    ->label(__('pim.woosync.col.failed'))
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'danger' : 'gray'),
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
            Section::make(__('pim.woosync.report.summary'))
                ->columns(3)
                ->schema([
                    TextEntry::make('user.name')->label(__('pim.woosync.col.user'))->placeholder('—'),
                    TextEntry::make('trigger')
                        ->label(__('pim.woosync.col.trigger'))
                        ->badge()
                        ->formatStateUsing(fn (string $state): string => __('pim.woosync.trigger.'.$state)),
                    TextEntry::make('status')
                        ->label(__('pim.field.status'))
                        ->badge()
                        ->formatStateUsing(fn (string $state): string => __('pim.woosync.status.'.$state))
                        ->color(fn (string $state): string => match ($state) {
                            'completed' => 'success',
                            'failed' => 'danger',
                            default => 'gray',
                        }),
                    TextEntry::make('started_at')->label(__('pim.woosync.report.started'))->dateTime('d/m/Y H:i:s')->placeholder('—'),
                    TextEntry::make('finished_at')->label(__('pim.woosync.report.finished'))->dateTime('d/m/Y H:i:s')->placeholder('—'),
                    TextEntry::make('total')->label(__('pim.woosync.col.total')),
                    TextEntry::make('created_count')->label(__('pim.woosync.col.created')),
                    TextEntry::make('updated_count')->label(__('pim.woosync.col.updated')),
                    TextEntry::make('skipped_count')->label(__('pim.woosync.col.skipped')),
                    TextEntry::make('failed_count')->label(__('pim.woosync.col.failed')),
                ]),

            TextEntry::make('running_hint')
                ->hiddenLabel()
                ->state(fn (): string => __('pim.woosync.report.running_hint'))
                ->color('gray')
                ->visible(fn (WooSyncRun $record): bool => $record->isRunning()),

            TextEntry::make('error_message')
                ->label(__('pim.woosync.report.error'))
                ->color('danger')
                ->visible(fn (WooSyncRun $record): bool => filled($record->error_message)),

            Section::make(__('pim.woosync.report.items'))
                ->visible(fn (WooSyncRun $record): bool => filled($record->items))
                ->schema([
                    RepeatableEntry::make('items')
                        ->hiddenLabel()
                        ->schema([
                            TextEntry::make('product')->label(__('pim.woosync.report.item_product')),
                            TextEntry::make('sku')->label(__('pim.field.sku'))->placeholder('—'),
                            TextEntry::make('result')
                                ->label(__('pim.woosync.report.item_result'))
                                ->badge()
                                ->formatStateUsing(fn (string $state): string => __('pim.woosync.result.'.$state))
                                ->color(fn (string $state): string => match ($state) {
                                    'created' => 'success',
                                    'updated' => 'info',
                                    'failed' => 'danger',
                                    default => 'gray',
                                }),
                            TextEntry::make('reason')->label(__('pim.woosync.report.item_reason'))->placeholder('—')->columnSpanFull(),
                        ])
                        ->columns(3),
                ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWooSyncRuns::route('/'),
            'view' => ViewWooSyncRun::route('/{record}'),
        ];
    }
}
