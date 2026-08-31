<?php

namespace Modules\ImportGestionali\Support;

/**
 * The outcome of matching one taxonomy column's pipe-separated cell against the
 * terms of that specific taxonomy. Drives the preview and, in a real run, the
 * pivot sync + report notes.
 *
 * Each entry in {@see $terms} is `{name: string, status: string, term_id: ?int}`
 * where status is one of the constants below.
 */
final readonly class TaxonomyResolution
{
    /** matched an existing term */
    public const FOUND = 'found';

    /** created on the fly during a real run */
    public const CREATED = 'created';

    /** would be created (dry run, with "create missing" on) */
    public const WILL_CREATE = 'will_create';

    /** no match and not created */
    public const MISSING = 'missing';

    /**
     * @param  list<array{name: string, status: string, term_id: ?int}>  $terms
     */
    public function __construct(
        public int $taxonomyId,
        public ?string $taxonomyName,
        public array $terms,
        public bool $gone = false,
    ) {}

    public static function gone(int $taxonomyId): self
    {
        return new self($taxonomyId, null, [], true);
    }

    /**
     * Ids of terms that resolved (found or created) — the set to attach.
     *
     * @return list<int>
     */
    public function resolvedIds(): array
    {
        $ids = [];

        foreach ($this->terms as $term) {
            if (in_array($term['status'], [self::FOUND, self::CREATED], true) && $term['term_id'] !== null) {
                $ids[] = $term['term_id'];
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * Names that did not resolve — one report note each.
     *
     * @return list<string>
     */
    public function missingNames(): array
    {
        $names = [];

        foreach ($this->terms as $term) {
            if ($term['status'] === self::MISSING) {
                $names[] = $term['name'];
            }
        }

        return $names;
    }
}
