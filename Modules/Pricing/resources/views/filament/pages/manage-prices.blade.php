<x-filament-panels::page>
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

        <div class="flex gap-2">
            {{ $this->saveViewAction }}
            {{ $this->updateViewAction }}
            {{ $this->deleteViewAction }}
        </div>
    </div>

    <div class="flex flex-wrap items-end gap-3">
        <label class="text-sm">
            <span class="fi-fo-field-wrp-label mb-1 block text-sm font-medium">{{ __('pim.field.search') }}</span>
            <x-filament::input.wrapper prefix-icon="heroicon-m-magnifying-glass">
                <x-filament::input type="text" wire:model.live.debounce.400ms="search" placeholder="{{ __('pim.grid.search_placeholder') }}" />
            </x-filament::input.wrapper>
        </label>

        <label class="text-sm">
            <span class="fi-fo-field-wrp-label mb-1 block text-sm font-medium">{{ __('pim.filter.price') }}</span>
            <x-filament::input.wrapper>
                <x-filament::input.select wire:model.live="hasPrice">
                    <option value="">{{ __('pim.option.price.all') }}</option>
                    <option value="yes">{{ __('pim.option.price.with') }}</option>
                    <option value="no">{{ __('pim.option.price.without') }}</option>
                </x-filament::input.select>
            </x-filament::input.wrapper>
        </label>

        <label class="text-sm">
            <span class="fi-fo-field-wrp-label mb-1 block text-sm font-medium">{{ __('pim.field.category') }}</span>
            <x-filament::input.wrapper>
                <x-filament::input.select wire:model.live="categoryTermId">
                    <option value="">{{ __('pim.option.any') }}</option>
                    @foreach ($this->categoryOptions() as $id => $label)
                        <option value="{{ $id }}">{{ $label }}</option>
                    @endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>
        </label>
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

    <x-filament-actions::modals />
</x-filament-panels::page>
