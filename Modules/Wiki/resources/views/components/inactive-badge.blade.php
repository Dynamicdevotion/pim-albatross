{{--
    Small "Non attivo" pill for a Wiki sidebar section whose commercial
    feature is currently switched off (see config('wiki.section_feature_flags')
    and DocSection::$inactive). Kept as its own component so every section
    that needs it — WooCommerce today, Lingue/Listini once those docs exist —
    renders it identically.
--}}
<x-filament::badge color="gray" size="xs">
    {{ __('pim.wiki.badge.inactive') }}
</x-filament::badge>
