<x-filament-panels::page>
    {{-- personal saved views: table filters + column-manager state --}}
    <div class="flex flex-wrap items-end gap-3">
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

    {{ $this->content }}

    <x-filament-actions::modals />
</x-filament-panels::page>
