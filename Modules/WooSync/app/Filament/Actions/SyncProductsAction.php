<?php

namespace Modules\WooSync\Filament\Actions;

use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
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
                EloquentCollection::make([$record]),
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
                $records,
                'bulk',
                $livewire,
            ))
            ->deselectRecordsAfterCompletion();
    }

    private static function connectionConfigured(): bool
    {
        return WooSyncSetting::current()->isConfigured();
    }

    private static function dispatch(EloquentCollection $records, string $trigger, Component $livewire): void
    {
        $ids = $records->map(static fn (Product $product): int => (int) $product->getKey())->unique()->values();

        $run = WooSyncRun::create([
            'user_id' => auth()->id(),
            'trigger' => $trigger,
            'status' => 'pending',
            'product_ids' => $ids->all(),
            'total' => $ids->count(),
        ]);

        if (self::weight($records) > (int) config('woosync.inline_max_products', 25)) {
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

    /**
     * A `variable` product costs far more than a `simple` one to sync —
     * attribute resolution, the parent, and one call per current variant —
     * so it counts for more than 1 toward the inline/queue threshold: the
     * parent plus its variant count, plus one more for the attribute
     * resolution overhead. A single "Sincronizza" on one variable product
     * still comfortably runs inline in the common case (a handful of
     * variants); only genuinely heavy selections — large bulk runs, or a
     * variable with many variants — get pushed to the queue.
     */
    private static function weight(EloquentCollection $records): int
    {
        $variableIds = $records->filter(fn (Product $product): bool => $product->isVariable())->pluck('id');

        $variantCounts = $variableIds->isEmpty()
            ? collect()
            : Product::query()
                ->whereIn('parent_id', $variableIds)
                ->selectRaw('parent_id, count(*) as c')
                ->groupBy('parent_id')
                ->pluck('c', 'parent_id');

        return $records->sum(function (Product $product) use ($variantCounts): int {
            if (! $product->isVariable()) {
                return 1;
            }

            return 2 + (int) ($variantCounts[$product->id] ?? 0);
        });
    }
}
