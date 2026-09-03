<?php

namespace Modules\Localization\Support;

use Closure;
use Illuminate\Support\Str;

/**
 * Turns a name into a URL-safe slug and, when that candidate is already
 * taken, appends `-2`, `-3`, … until one is free — the single place this
 * algorithm lives, shared by every screen that generates a slug from a name
 * (products, taxonomies, taxonomy terms).
 *
 * The caller decides what "taken" means by supplying `$exists`: a closure
 * that checks one candidate against whatever scope applies (a language, a
 * taxonomy, the record being edited, …).
 */
class SlugGenerator
{
    /**
     * @param  Closure(string): bool  $exists  Returns true when the candidate
     *                                         slug is already in use.
     */
    public static function unique(string $base, Closure $exists): string
    {
        $slug = Str::slug($base);
        $slug = $slug !== '' ? $slug : 'item';

        $candidate = $slug;
        $suffix = 2;

        while ($exists($candidate)) {
            $candidate = $slug.'-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }
}
