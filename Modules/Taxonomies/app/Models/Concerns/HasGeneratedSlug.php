<?php

namespace Modules\Taxonomies\Models\Concerns;

use Illuminate\Support\Str;

/**
 * Fills an empty `slug` from `name` on save, keeping it unique (optionally
 * within a scope declared by slugScope()).
 */
trait HasGeneratedSlug
{
    public static function bootHasGeneratedSlug(): void
    {
        static::saving(function ($model): void {
            if (filled($model->slug)) {
                return;
            }

            $model->slug = $model->generateUniqueSlug();
        });
    }

    protected function generateUniqueSlug(): string
    {
        $base = Str::slug((string) $this->name) ?: 'item';
        $slug = $base;
        $suffix = 2;

        while ($this->slugExists($slug)) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    protected function slugExists(string $slug): bool
    {
        $query = static::query()->where('slug', $slug);

        if ($this->exists) {
            $query->whereKeyNot($this->getKey());
        }

        foreach ($this->slugScope() as $column => $value) {
            $query->where($column, $value);
        }

        return $query->exists();
    }

    /**
     * Extra where-clauses that scope slug uniqueness.
     *
     * @return array<string, mixed>
     */
    protected function slugScope(): array
    {
        return [];
    }
}
