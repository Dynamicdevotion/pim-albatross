<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            {{ __('pim.dashboard.import_issues.heading') }}
        </x-slot>

        @if ($record)
            <x-slot name="headerEnd">
                <x-filament::link :href="$reportUrl" size="sm" icon="heroicon-m-arrow-top-right-on-square">
                    {{ __('pim.dashboard.import_issues.view_report') }}
                </x-filament::link>
            </x-slot>

            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ __('pim.dashboard.import_issues.subheading', [
                    'file' => $record->original_filename,
                    'date' => $record->created_at?->format('d/m/Y H:i'),
                    'count' => $record->skipped_count,
                ]) }}
            </p>

            <ul class="mt-3 space-y-1 text-sm">
                @foreach ($issues as $issue)
                    <li class="flex gap-2">
                        <x-filament::icon icon="heroicon-m-x-circle" class="mt-0.5 h-4 w-4 shrink-0 text-danger-500" />
                        <span>{{ $issue['reason'] ?? '' }}</span>
                    </li>
                @endforeach
            </ul>

            @if ($remaining > 0)
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    {{ __('pim.dashboard.import_issues.more', ['count' => $remaining]) }}
                </p>
            @endif
        @else
            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ __('pim.dashboard.import_issues.empty') }}
            </p>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
