<?php

namespace Modules\SavedViews\Filament\Concerns;

use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Modules\SavedViews\Models\SavedView;

/**
 * Adds a personal "saved views" dropdown (filters + visible columns) to any
 * Filament page or Livewire component.
 *
 * The consumer implements the three abstract methods; everything else — the
 * `savedViewId` property, the option list and the save/update/delete actions —
 * comes from here. Designed to be reused across the panel (price grid now,
 * resource list pages later by snapshotting tableFilters / toggledTableColumns).
 */
trait InteractsWithSavedViews
{
    public ?int $savedViewId = null;

    /**
     * A stable key identifying the screen these views belong to,
     * e.g. "pricing.prices".
     */
    abstract public function savedViewResourceKey(): string;

    /**
     * The current filter + column state to persist.
     *
     * @return array{filters?: array<string, mixed>, columns?: array<int, string>}
     */
    abstract public function captureViewState(): array;

    /**
     * Restore a previously saved state.
     *
     * @param  array{filters?: array<string, mixed>, columns?: array<int, string>}  $state
     */
    abstract public function applyViewState(array $state): void;

    /**
     * @return array<int, string>
     */
    public function savedViewOptions(): array
    {
        return SavedView::query()
            ->forUser($this->currentUserId())
            ->forResource($this->savedViewResourceKey())
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    public function updatedSavedViewId(): void
    {
        $this->loadSavedView();
    }

    public function loadSavedView(): void
    {
        if (blank($this->savedViewId)) {
            return;
        }

        $view = SavedView::query()
            ->forUser($this->currentUserId())
            ->forResource($this->savedViewResourceKey())
            ->whereKey($this->savedViewId)
            ->first();

        if ($view === null) {
            $this->savedViewId = null;

            return;
        }

        $this->applyViewState([
            'filters' => $view->filters ?? [],
            'columns' => $view->columns ?? [],
        ]);
    }

    public function saveViewAction(): Action
    {
        return Action::make('saveView')
            ->label(__('pim.action.save_view'))
            ->icon('heroicon-o-bookmark')
            ->schema([
                TextInput::make('name')
                    ->label(__('pim.field.name'))
                    ->required()
                    ->maxLength(255),
            ])
            ->action(function (array $data): void {
                $state = $this->captureViewState();

                $view = SavedView::updateOrCreate(
                    [
                        'user_id' => $this->currentUserId(),
                        'resource' => $this->savedViewResourceKey(),
                        'name' => $data['name'],
                    ],
                    [
                        'filters' => $state['filters'] ?? [],
                        'columns' => $state['columns'] ?? [],
                    ],
                );

                $this->savedViewId = $view->getKey();

                Notification::make()
                    ->title(__('pim.notification.view_saved', ['name' => $view->name]))
                    ->success()
                    ->send();
            });
    }

    public function updateViewAction(): Action
    {
        return Action::make('updateView')
            ->label(__('pim.action.update_view'))
            ->icon('heroicon-o-arrow-path')
            ->visible(fn (): bool => filled($this->savedViewId))
            ->requiresConfirmation()
            ->action(function (): void {
                $view = $this->currentSavedView();

                if ($view === null) {
                    return;
                }

                $state = $this->captureViewState();

                $view->update([
                    'filters' => $state['filters'] ?? [],
                    'columns' => $state['columns'] ?? [],
                ]);

                Notification::make()
                    ->title(__('pim.notification.view_updated', ['name' => $view->name]))
                    ->success()
                    ->send();
            });
    }

    public function deleteViewAction(): Action
    {
        return Action::make('deleteView')
            ->label(__('pim.action.delete_view'))
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->visible(fn (): bool => filled($this->savedViewId))
            ->requiresConfirmation()
            ->action(function (): void {
                $this->currentSavedView()?->delete();
                $this->savedViewId = null;

                Notification::make()
                    ->title(__('pim.notification.view_deleted'))
                    ->success()
                    ->send();
            });
    }

    protected function currentSavedView(): ?SavedView
    {
        if (blank($this->savedViewId)) {
            return null;
        }

        return SavedView::query()
            ->forUser($this->currentUserId())
            ->forResource($this->savedViewResourceKey())
            ->whereKey($this->savedViewId)
            ->first();
    }

    protected function currentUserId(): int
    {
        return (int) auth()->id();
    }
}
