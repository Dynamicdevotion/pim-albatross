<x-filament-panels::page>
    {{-- toolbar: list, saved views, filters --}}
    <div class="flex flex-wrap items-end gap-3">
        <label class="text-sm">
            <span class="fi-fo-field-wrp-label mb-1 block text-sm font-medium">Price list</span>
            <x-filament::input.wrapper>
                <x-filament::input.select wire:model.live="priceListId">
                    @foreach ($this->priceListOptions() as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>
        </label>

        <label class="text-sm">
            <span class="fi-fo-field-wrp-label mb-1 block text-sm font-medium">Saved view</span>
            <x-filament::input.wrapper>
                <x-filament::input.select wire:model.live="savedViewId">
                    <option value="">— none —</option>
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
            <span class="fi-fo-field-wrp-label mb-1 block text-sm font-medium">Search</span>
            <x-filament::input.wrapper prefix-icon="heroicon-m-magnifying-glass">
                <x-filament::input type="text" wire:model.live.debounce.400ms="search" placeholder="Name or SKU" />
            </x-filament::input.wrapper>
        </label>

        <label class="text-sm">
            <span class="fi-fo-field-wrp-label mb-1 block text-sm font-medium">Price</span>
            <x-filament::input.wrapper>
                <x-filament::input.select wire:model.live="hasPrice">
                    <option value="">All products</option>
                    <option value="yes">With a price</option>
                    <option value="no">Without a price</option>
                </x-filament::input.select>
            </x-filament::input.wrapper>
        </label>

        <label class="text-sm">
            <span class="fi-fo-field-wrp-label mb-1 block text-sm font-medium">Category</span>
            <x-filament::input.wrapper>
                <x-filament::input.select wire:model.live="categoryTermId">
                    <option value="">Any</option>
                    @foreach ($this->categoryOptions() as $id => $label)
                        <option value="{{ $id }}">{{ $label }}</option>
                    @endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>
        </label>
    </div>

    {{-- columns + bulk actions --}}
    <div class="flex flex-wrap items-center gap-4">
        <span class="text-sm font-medium">Columns:</span>
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
            Showing the first {{ number_format(1000) }} products — narrow the filters to reach the rest.
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
