<x-filament-panels::page>
    <style>
        .wiki-page-content .wiki-heading-permalink {
            margin-inline-start: 0.5rem;
            opacity: 0;
            text-decoration: none;
            transition: opacity 0.15s ease-in-out;
        }
    
        .wiki-page-content h2:hover .wiki-heading-permalink,
        .wiki-page-content h3:hover .wiki-heading-permalink {
            opacity: 0.6;
        }
    </style>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-[260px_1fr] xl:grid-cols-[260px_1fr_220px]">
        <aside class="space-y-4">
            <x-filament::input.wrapper>
                <x-filament::input
                    type="search"
                    wire:model.live.debounce.300ms="search"
                    placeholder="{{ __('pim.wiki.search.placeholder') }}"
                />
            </x-filament::input.wrapper>

            <nav class="space-y-5">
                @forelse ($this->filteredTree as $section)
                    <div>
                        <button
                            type="button"
                            wire:click="$set('activePage', '{{ $section->indexPage->slug }}')"
                            @class([
                                'flex w-full items-center gap-1.5 rounded-md px-2 py-1 text-start text-sm font-semibold text-gray-950 dark:text-white',
                                'bg-primary-50 text-primary-600 dark:bg-primary-500/10 dark:text-primary-400' => $this->activeDocPage?->slug === $section->indexPage->slug,
                            ])
                        >
                            <x-filament::icon icon="heroicon-o-folder-open" class="h-4 w-4 shrink-0 text-gray-400 dark:text-gray-500" />
                            <span>{!! $this->highlight($section->title) !!}</span>
                        </button>

                        @if ($section->pages !== [])
                            <ul class="mt-1 space-y-0.5 border-s border-gray-200 ps-3 dark:border-gray-700">
                                @foreach ($section->pages as $page)
                                    <li>
                                        <button
                                            type="button"
                                            wire:click="$set('activePage', '{{ $page->slug }}')"
                                            @class([
                                                'flex w-full items-center gap-1.5 rounded-md px-2 py-1 text-start text-sm text-gray-600 dark:text-gray-400',
                                                'bg-primary-50 font-medium text-primary-600 dark:bg-primary-500/10 dark:text-primary-400' => $this->activeDocPage?->slug === $page->slug,
                                            ])
                                        >
                                            <x-filament::icon icon="heroicon-o-document-text" class="h-4 w-4 shrink-0 text-gray-400 dark:text-gray-500" />
                                            <span>{!! $this->highlight($page->title) !!}</span>
                                        </button>

                                        @if ($snippet = $this->searchSnippet($page))
                                            <p class="ms-6 me-1 mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                                {!! $this->highlight($snippet) !!}
                                            </p>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @elseif ($snippet = $this->searchSnippet($section->indexPage))
                            <p class="ms-6 me-1 mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                {!! $this->highlight($snippet) !!}
                            </p>
                        @endif
                    </div>
                @empty
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('pim.wiki.search.no_results') }}</p>
                @endforelse
            </nav>
        </aside>

        <div class="fi-prose max-w-none wiki-page-content">
            {!! $this->pageHtml !!}
        </div>

        @if ($this->pageToc !== [])
            <aside class="hidden xl:block">
                <div class="sticky top-20 space-y-2">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        {{ __('pim.wiki.toc.heading') }}
                    </p>

                    <ul class="space-y-1 border-s border-gray-200 ps-3 text-sm dark:border-gray-700">
                        @foreach ($this->pageToc as $entry)
                            <li class="{{ $entry['level'] === 3 ? 'ms-3' : '' }}">
                                <a
                                    href="#{{ $entry['id'] }}"
                                    class="block text-gray-600 hover:text-primary-600 dark:text-gray-400 dark:hover:text-primary-400"
                                >
                                    {{ $entry['text'] }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </aside>
        @endif
    </div>
</x-filament-panels::page>
