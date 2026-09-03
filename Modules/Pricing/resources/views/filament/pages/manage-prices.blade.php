<x-filament-panels::page
    x-data="{ filtersOpen: false }"
    x-on:keydown.escape.window="filtersOpen = false"
>
    {{-- toolbar: list, saved views, filters --}}
    <div class="flex flex-wrap items-end gap-3">
        <label class="text-sm">
            <span class="fi-fo-field-wrp-label mb-1 block text-sm font-medium">{{ __('pim.field.price_list') }}</span>
            <x-filament::input.wrapper>
                <x-filament::input.select wire:model.live="priceListId">
                    @foreach ($this->priceListOptions() as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>
        </label>

        <label class="text-sm">
            <span class="fi-fo-field-wrp-label mb-1 block text-sm font-medium">{{ __('pim.field.saved_view') }}</span>
            <x-filament::input.wrapper>
                <x-filament::input.select wire:model.live="savedViewId">
                    <option value="">{{ __('pim.option.none') }}</option>
                    @foreach ($this->savedViewOptions() as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>
        </label>

        <div class="flex flex-wrap gap-2">
            {{ $this->saveViewAction }}
            {{ $this->updateViewAction }}
            {{ $this->deleteViewAction }}

            @php($activeFiltersCount = $this->getTable()->getActiveFiltersCount())

            <x-filament::button
                type="button"
                color="gray"
                icon="heroicon-m-funnel"
                x-on:click="filtersOpen = true"
                :badge="$activeFiltersCount ?: null"
            >
                {{ __('filament-tables::table.actions.filter.label') }}
            </x-filament::button>
        </div>
    </div>

    {{-- columns + bulk actions --}}
    <div class="flex flex-wrap items-center gap-4">
        <span class="text-sm font-medium">{{ __('pim.field.columns') }}:</span>
        @foreach ($this->columnCatalogue() as $key => $label)
            <label class="inline-flex items-center gap-1.5 text-sm">
                <input type="checkbox" wire:model.live="visibleColumns" value="{{ $key }}"
                       class="fi-checkbox-input rounded border-gray-300 dark:border-gray-600" />
                {{ $label }}
            </label>
        @endforeach

        <div class="ms-auto flex gap-2">
            {{ $this->setFixedPriceAction }}
            {{ $this->adjustSelectionAction }}
            {{ $this->adjustCategoryAction }}
        </div>
    </div>

    @if ($this->gridCapped())
        <x-filament::badge color="warning">
            {{ __('pim.grid.row_cap', ['count' => number_format(1000)]) }}
        </x-filament::badge>
    @endif

    <div
        wire:ignore
        x-data="pricesGrid({
            rows: @js($this->rows()),
            columns: @js(array_values($this->visibleColumns)),
            headers: @js($this->gridHeaders()),
        })"
    >
        <div x-ref="grid"></div>
    </div>

    {{-- Filter drawer: slides up from the bottom of the screen. Same set as
         the products list — see ManagePrices::table(). --}}
    <template x-teleport="body">
        <div
            x-cloak
            x-show="filtersOpen"
            class="pim-filters-drawer-root"
            role="dialog"
            aria-modal="true"
            aria-label="{{ __('filament-tables::table.filters.heading') }}"
        >
            <div
                x-show="filtersOpen"
                x-transition.opacity
                x-on:click="filtersOpen = false"
                class="pim-filters-drawer-overlay"
            ></div>

            <div
                x-show="filtersOpen"
                x-transition:enter="pim-filters-drawer-transition"
                x-transition:enter-start="pim-filters-drawer-offscreen"
                x-transition:enter-end="pim-filters-drawer-onscreen"
                x-transition:leave="pim-filters-drawer-transition"
                x-transition:leave-start="pim-filters-drawer-onscreen"
                x-transition:leave-end="pim-filters-drawer-offscreen"
                x-trap.noscroll="filtersOpen"
                class="pim-filters-drawer"
            >
                <div class="pim-filters-drawer-header">
                    <h2 class="pim-filters-drawer-title">
                        {{ __('filament-tables::table.filters.heading') }}
                    </h2>

                    <x-filament::button
                        type="button"
                        color="gray"
                        size="sm"
                        icon="heroicon-m-x-mark"
                        x-on:click="filtersOpen = false"
                    >
                        {{ __('filament::components/modal.actions.close.label') }}
                    </x-filament::button>
                </div>

                <div class="pim-filters-drawer-body">
                    {{ $this->getTableFiltersForm() }}
                </div>

                <div class="pim-filters-drawer-footer">
                    <x-filament::button
                        type="button"
                        wire:click="applyTableFilters"
                        wire:loading.attr="disabled"
                        wire:target="applyTableFilters"
                        x-on:click="filtersOpen = false"
                    >
                        {{ __('filament-tables::table.filters.actions.apply.label') }}
                    </x-filament::button>

                    <x-filament::button
                        type="button"
                        color="gray"
                        wire:click="resetTableFiltersForm"
                        wire:target="resetTableFiltersForm"
                    >
                        {{ __('filament-tables::table.filters.actions.reset.label') }}
                    </x-filament::button>
                </div>
            </div>
        </div>
    </template>

    <x-filament-actions::modals />
</x-filament-panels::page>
