<x-filament-panels::page>
    <form wire:submit="import">
        {{ $this->form }}
    </form>

    <x-filament-actions::modals />
</x-filament-panels::page>
