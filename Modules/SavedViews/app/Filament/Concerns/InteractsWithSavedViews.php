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
 *
 * The active view also survives navigating away and back within the same
 * browser session (Laravel session, not a database column): whichever view
 * was last loaded, saved or cleared on a given screen is remembered under a
 * key scoped to that screen's {@see savedViewResourceKey()}. It's restored
 * from the *rendering* lifecycle hook rather than `mount`: a consumer built
 * on `Filament\Tables\Concerns\InteractsWithTable` (the price grid, any
 * resource list page) only finishes building its `Table` instance in its own
 * `booted` hook, and applying a view needs that table to already exist (it
 * calls `getTableFiltersForm()`). `rendering` always runs after every
 * trait's `mount`/`booted` phase has finished, so it works regardless of
 * which order the consumer's traits happen to be declared in — see
 * {@see renderingInteractsWithSavedViews()}, which Livewire calls
 * automatically.
 */
trait InteractsWithSavedViews
{
    public ?int $savedViewId = null;

    /**
     * Set for exactly one render — the first one after a mount that found a
     * remembered view — then consumed and cleared.
     */
    protected bool $shouldRestoreSavedViewOnRender = false;

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

    /**
     * Livewire calls `mount{TraitName}()` hooks automatically. Only stages
     * the id here (cheap, no table access needed) — the actual restore
     * happens on the next render, once the consumer's table (if any) exists.
     */
    public function mountInteractsWithSavedViews(): void
    {
        $sessionViewId = session($this->savedViewSessionKey());

        if ($sessionViewId === null) {
            return;
        }

        $this->savedViewId = $sessionViewId;
        $this->shouldRestoreSavedViewOnRender = true;
    }

    /**
     * Livewire calls `rendering{TraitName}()` hooks automatically, after
     * every trait's `mount`/`booted` phase has finished — see the class
     * docblock for why this can't happen any earlier.
     */
    public function renderingInteractsWithSavedViews($view = null, $data = null): void
    {
        if (! $this->shouldRestoreSavedViewOnRender) {
            return;
        }

        $this->shouldRestoreSavedViewOnRender = false;
        $this->loadSavedView();
    }

    public function updatedSavedViewId(): void
    {
        $this->loadSavedView();
    }

    public function loadSavedView(): void
    {
        if (blank($this->savedViewId)) {
            $this->rememberSavedViewInSession();

            return;
        }

        $view = SavedView::query()
            ->forUser($this->currentUserId())
            ->forResource($this->savedViewResourceKey())
            ->whereKey($this->savedViewId)
            ->first();

        if ($view === null) {
            $this->savedViewId = null;
            $this->rememberSavedViewInSession();

            return;
        }

        $this->applyViewState([
            'filters' => $view->filters ?? [],
            'columns' => $view->columns ?? [],
        ]);

        $this->rememberSavedViewInSession();
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
                $this->rememberSavedViewInSession();

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
                $this->rememberSavedViewInSession();

                Notification::make()
                    ->title(__('pim.notification.view_deleted'))
                    ->success()
                    ->send();
            });
    }

    protected function savedViewSessionKey(): string
    {
        return 'saved_view.'.$this->savedViewResourceKey();
    }

    /**
     * Keeps the session's remembered view in sync with `$savedViewId` —
     * called after every load, save or delete so the next visit within this
     * browser session picks up exactly where the user left off.
     */
    protected function rememberSavedViewInSession(): void
    {
        if (blank($this->savedViewId)) {
            session()->forget($this->savedViewSessionKey());

            return;
        }

        session()->put($this->savedViewSessionKey(), $this->savedViewId);
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
