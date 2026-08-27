<x-filament-panels::page>
    <div class="max-w-xs">
        <x-filament::input.wrapper>
            <x-filament::input.select wire:model.live="priceListId" aria-label="Price list">
                @foreach ($this->priceListOptions() as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </x-filament::input.select>
        </x-filament::input.wrapper>
    </div>

    {{ $this->table }}
</x-filament-panels::page>
