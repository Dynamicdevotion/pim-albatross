@props([
    'page',
    'anchor' => null,
    'label' => null,
])

{{--
    Thin wrapper around Filament's own icon-button so a help icon dropped
    anywhere in the panel (a form, a section, a page) looks and behaves like
    every other icon action already in the UI — tooltip, sizing, focus ring,
    dark mode all come for free.

    `page` is the DocPage slug exactly as produced by DocTree — e.g.
    "02-prodotti/varianti" for docs/02-prodotti/varianti.md, or just
    "02-prodotti" for that section's own index.md. `anchor` is the heading
    slug rendered by MarkdownRenderer's HeadingPermalink extension, matching
    the `##`/`###` text (e.g. "come-generare-varianti").
--}}
<x-filament::icon-button
    tag="a"
    :href="\Modules\Wiki\Filament\Pages\GuidaPage::getUrl(['p' => $page]).($anchor ? '#'.$anchor : '')"
    target="_blank"
    icon="heroicon-o-question-mark-circle"
    :tooltip="$label ?? __('pim.wiki.help_icon.tooltip')"
    color="gray"
    size="sm"
    {{ $attributes }}
/>
