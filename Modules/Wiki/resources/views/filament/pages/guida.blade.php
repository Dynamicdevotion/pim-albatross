<x-filament-panels::page>
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-[280px_1fr]">
        <aside class="space-y-4">
            <x-filament::input.wrapper>
                <x-filament::input
                    type="search"
                    wire:model.live.debounce.300ms="search"
                    placeholder="{{ __('pim.wiki.search.placeholder') }}"
                />
            </x-filament::input.wrapper>

            <nav class="space-y-4">
                @forelse ($this->filteredTree as $section)
                    <div>
                        <button
                            type="button"
                            wire:click="$set('activePage', '{{ $section->indexPage->slug }}')"
                            @class([
                                'block w-full text-start text-sm font-semibold text-gray-950 dark:text-white',
                                'text-primary-600 dark:text-primary-400' => $this->activeDocPage?->slug === $section->indexPage->slug,
                            ])
                        >
                            {{ $section->title }}
                        </button>

                        @if ($section->pages !== [])
                            <ul class="mt-1 space-y-1 border-s border-gray-200 ps-3 dark:border-gray-700">
                                @foreach ($section->pages as $page)
                                    <li>
                                        <button
                                            type="button"
                                            wire:click="$set('activePage', '{{ $page->slug }}')"
                                            @class([
                                                'block w-full text-start text-sm text-gray-600 dark:text-gray-400',
                                                'font-medium text-primary-600 dark:text-primary-400' => $this->activeDocPage?->slug === $page->slug,
                                            ])
                                        >
                                            {{ $page->title }}
                                        </button>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                @empty
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('pim.wiki.search.no_results') }}</p>
                @endforelse
            </nav>
        </aside>

        <div class="prose max-w-none dark:prose-invert">
            {!! $this->pageHtml !!}
        </div>
    </div>
</x-filament-panels::page>
