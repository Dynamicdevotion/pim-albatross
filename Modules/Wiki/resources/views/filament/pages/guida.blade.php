<x-filament-panels::page>
    {{--
        No Tailwind utility class below this comment is new — the Filament
        panel has no ->viteTheme() registered (see AdminPanelProvider), so it
        renders only Filament's own precompiled CSS, never this project's
        Tailwind build. A brand-new utility class (or an arbitrary-value one
        like grid-cols-[260px_1fr]) has no compiled rule anywhere and simply
        does nothing. Everything specific to this page's layout is therefore
        plain hand-written CSS instead, scoped under .wiki-guida; colors reuse
        Filament's own --primary-*/--gray-* custom properties so they stay in
        sync with the panel's configured theme (including dark mode, toggled
        via the :where(.dark, .dark *) selector Filament itself uses).
    --}}
    <style>
        .wiki-guida {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1.5rem;
        }

        @media (min-width: 1024px) {
            .wiki-guida {
                grid-template-columns: 260px 1fr;
            }
        }

        @media (min-width: 1280px) {
            .wiki-guida {
                grid-template-columns: 260px 1fr 220px;
            }
        }

        .wiki-guida-sidebar {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .wiki-guida-nav {
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
        }

        .wiki-guida-section-title {
            display: flex;
            align-items: center;
            gap: 0.375rem;
            width: 100%;
            border: none;
            background: none;
            border-radius: 0.375rem;
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
            font-weight: 600;
            text-align: start;
            color: var(--gray-950);
            cursor: pointer;
        }

        :where(.dark, .dark *) .wiki-guida-section-title {
            color: #fff;
        }

        .wiki-guida-icon {
            width: 1rem;
            height: 1rem;
            flex-shrink: 0;
            color: var(--gray-400);
        }

        .wiki-guida-pages {
            list-style: none;
            margin: 0.25rem 0 0;
            padding: 0 0 0 0.75rem;
            border-inline-start: 1px solid var(--gray-200);
            display: flex;
            flex-direction: column;
            gap: 0.125rem;
        }

        :where(.dark, .dark *) .wiki-guida-pages {
            border-color: var(--gray-700);
        }

        .wiki-guida-page-link {
            display: flex;
            align-items: center;
            gap: 0.375rem;
            width: 100%;
            border: none;
            background: none;
            border-radius: 0.375rem;
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
            text-align: start;
            color: var(--gray-600);
            cursor: pointer;
        }

        :where(.dark, .dark *) .wiki-guida-page-link {
            color: var(--gray-400);
        }

        .wiki-guida-section-title.is-active,
        .wiki-guida-page-link.is-active {
            background: var(--primary-50);
            color: var(--primary-600);
        }

        .wiki-guida-page-link.is-active {
            font-weight: 500;
        }

        :where(.dark, .dark *) .wiki-guida-section-title.is-active,
        :where(.dark, .dark *) .wiki-guida-page-link.is-active {
            background: color-mix(in oklab, var(--primary-500) 15%, transparent);
            color: var(--primary-400);
        }

        .wiki-guida-snippet {
            margin: 0.125rem 0.25rem 0 1.5rem;
            font-size: 0.75rem;
            color: var(--gray-500);
        }

        :where(.dark, .dark *) .wiki-guida-snippet {
            color: var(--gray-400);
        }

        .wiki-guida-empty {
            font-size: 0.875rem;
            color: var(--gray-500);
        }

        .wiki-guida mark,
        .wiki-guida-snippet mark {
            background: var(--primary-100);
            color: var(--primary-700);
            border-radius: 0.125rem;
            padding: 0 0.125rem;
        }

        :where(.dark, .dark *) .wiki-guida mark,
        :where(.dark, .dark *) .wiki-guida-snippet mark {
            background: color-mix(in oklab, var(--primary-500) 30%, transparent);
            color: var(--primary-300);
        }

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

        .wiki-page-content h2,
        .wiki-page-content h3 {
            scroll-margin-top: 5rem;
        }

        .wiki-guida-toc {
            display: none;
        }

        @media (min-width: 1280px) {
            .wiki-guida-toc {
                display: block;
                position: sticky;
                top: 5rem;
                align-self: start;
            }
        }

        .wiki-guida-toc-heading {
            margin: 0 0 0.5rem;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--gray-500);
        }

        :where(.dark, .dark *) .wiki-guida-toc-heading {
            color: var(--gray-400);
        }

        .wiki-guida-toc-list {
            list-style: none;
            margin: 0;
            padding: 0 0 0 0.75rem;
            border-inline-start: 1px solid var(--gray-200);
            font-size: 0.875rem;
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        :where(.dark, .dark *) .wiki-guida-toc-list {
            border-color: var(--gray-700);
        }

        .wiki-guida-toc-item.is-sub {
            margin-inline-start: 0.75rem;
        }

        .wiki-guida-toc-item a {
            display: block;
            color: var(--gray-600);
            text-decoration: none;
        }

        .wiki-guida-toc-item a:hover {
            color: var(--primary-600);
        }

        :where(.dark, .dark *) .wiki-guida-toc-item a {
            color: var(--gray-400);
        }

        :where(.dark, .dark *) .wiki-guida-toc-item a:hover {
            color: var(--primary-400);
        }
    </style>

    <div class="wiki-guida">
        <aside class="wiki-guida-sidebar">
            <x-filament::input.wrapper>
                <x-filament::input
                    type="search"
                    wire:model.live.debounce.300ms="search"
                    placeholder="{{ __('pim.wiki.search.placeholder') }}"
                />
            </x-filament::input.wrapper>

            <nav class="wiki-guida-nav">
                @forelse ($this->filteredTree as $section)
                    <div>
                        <button
                            type="button"
                            wire:click="$set('activePage', '{{ $section->indexPage->slug }}')"
                            @class(['wiki-guida-section-title', 'is-active' => $this->activeDocPage?->slug === $section->indexPage->slug])
                        >
                            <x-filament::icon icon="heroicon-o-folder-open" class="wiki-guida-icon" />
                            <span>{!! $this->highlight($section->title) !!}</span>
                            @if ($section->inactive)
                                <x-wiki::inactive-badge />
                            @endif
                        </button>

                        @if ($section->pages !== [])
                            <ul class="wiki-guida-pages">
                                @foreach ($section->pages as $page)
                                    <li>
                                        <button
                                            type="button"
                                            wire:click="$set('activePage', '{{ $page->slug }}')"
                                            @class(['wiki-guida-page-link', 'is-active' => $this->activeDocPage?->slug === $page->slug])
                                        >
                                            <x-filament::icon icon="heroicon-o-document-text" class="wiki-guida-icon" />
                                            <span>{!! $this->highlight($page->title) !!}</span>
                                        </button>

                                        @if ($snippet = $this->searchSnippet($page))
                                            <p class="wiki-guida-snippet">{!! $this->highlight($snippet) !!}</p>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @elseif ($snippet = $this->searchSnippet($section->indexPage))
                            <p class="wiki-guida-snippet">{!! $this->highlight($snippet) !!}</p>
                        @endif
                    </div>
                @empty
                    <p class="wiki-guida-empty">{{ __('pim.wiki.search.no_results') }}</p>
                @endforelse
            </nav>
        </aside>

        <div class="fi-prose wiki-page-content">
            {!! $this->pageHtml !!}
        </div>

        @if ($this->pageToc !== [])
            <aside class="wiki-guida-toc">
                <p class="wiki-guida-toc-heading">{{ __('pim.wiki.toc.heading') }}</p>

                <ul class="wiki-guida-toc-list">
                    @foreach ($this->pageToc as $entry)
                        <li @class(['wiki-guida-toc-item', 'is-sub' => $entry['level'] === 3])>
                            <a href="#{{ $entry['id'] }}">{{ $entry['text'] }}</a>
                        </li>
                    @endforeach
                </ul>
            </aside>
        @endif
    </div>
</x-filament-panels::page>
