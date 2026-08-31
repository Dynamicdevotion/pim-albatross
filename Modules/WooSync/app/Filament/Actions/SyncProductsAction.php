<?php

namespace Modules\WooSync\Filament\Actions;

use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Livewire\Component;
use Modules\Products\Models\Product;
use Modules\WooSync\Filament\Resources\WooSyncRuns\WooSyncRunResource;
use Modules\WooSync\Jobs\RunWooSync;
use Modules\WooSync\Models\WooSyncRun;
use Modules\WooSync\Models\WooSyncSetting;
use Modules\WooSync\Support\WooSyncRunner;

/**
 * "Sincronizza con WooCommerce" — the manual trigger, as a single-row record
 * action on the products list and as a bulk action. A small selection runs
 * inline; past `woosync.inline_max_products` it is queued as a
 * {@see RunWooSync} job (needs the scheduled `queue:work --stop-when-empty`).
 * Either way the user is taken to the run's report page.
 *
 * Both actions are hidden until the WooCommerce connection is configured.
 */
class SyncProductsAction
{
    public static function record(): Action
    {
        return Action::make('woosync')
            ->label(__('pim.woosync.action.sync'))
            ->icon('heroicon-o-arrow-path-rounded-square')
            ->color('gray')
            ->visible(fn (): bool => self::connectionConfigured())
            ->requiresConfirmation()
            ->modalHeading(__('pim.woosync.action.sync'))
            ->modalDescription(fn (Product $record): string => __('pim.woosync.confirm.single', [
                'product' => $record->sku ?: '#'.$record->getKey(),
            ]))
            ->action(fn (Product $record, Component $livewire) => self::dispatch(
                collect([$record->getKey()]),
                'single',
                $livewire,
            ));
    }

    public static function bulk(): BulkAction
    {
        return BulkAction::make('woosync')
            ->label(__('pim.woosync.action.sync'))
            ->icon('heroicon-o-arrow-path-rounded-square')
            ->visible(fn (): bool => self::connectionConfigured())
            ->requiresConfirmation()
            ->modalHeading(__('pim.woosync.action.sync_bulk'))
            ->modalDescription(__('pim.woosync.confirm.bulk'))
            ->action(fn (EloquentCollection $records, Component $livewire) => self::dispatch(
                $records->map(fn (Product $product): int => $product->getKey()),
                'bulk',
                $livewire,
            ))
            ->deselectRecordsAfterCompletion();
    }

    private static function connectionConfigured(): bool
    {
        return WooSyncSetting::current()->isConfigured();
    }

    /**
     * @param  Collection<int, int>  $ids
     */
    private static function dispatch(Collection $ids, string $trigger, Component $livewire): void
    {
        $ids = $ids->map(static fn ($id): int => (int) $id)->unique()->values();

        $run = WooSyncRun::create([
            'user_id' => auth()->id(),
            'trigger' => $trigger,
            'status' => 'pending',
            'product_ids' => $ids->all(),
            'total' => $ids->count(),
        ]);

        if ($ids->count() > (int) config('woosync.inline_max_products', 25)) {
            RunWooSync::dispatch($run);

            Notification::make()
                ->title(__('pim.woosync.notify.queued'))
                ->success()
                ->send();
        } else {
            app(WooSyncRunner::class)->run($run);
            $run->refresh();

            Notification::make()
                ->title(__('pim.woosync.notify.done', [
                    'created' => $run->created_count,
                    'updated' => $run->updated_count,
                    'skipped' => $run->skipped_count,
                    'failed' => $run->failed_count,
                ]))
                ->status($run->isFailed() || $run->failed_count > 0 ? 'warning' : 'success')
                ->send();
        }

        $livewire->redirect(WooSyncRunResource::getUrl('view', ['record' => $run]));
    }
}
