<x-filament-panels::page>
    <div @if ($this->record->isRunning()) wire:poll.5s @endif>
        {{ $this->content }}
    </div>
</x-filament-panels::page>
