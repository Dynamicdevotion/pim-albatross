<?php

namespace Modules\Localization\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Localization\Models\Language;
use Modules\Localization\Support\LanguageCatalog;

/**
 * Populates / tops up the `languages` table from LanguageCatalog. Idempotent:
 * existing rows keep their admin-controlled `active` flag; only `name` and
 * `is_base` are re-synced.
 */
class LanguageSeeder extends Seeder
{
    public function run(): void
    {
        foreach (LanguageCatalog::all() as $language) {
            $existing = Language::query()->where('code', $language['code'])->first();

            if ($existing) {
                $existing->update([
                    'name' => $language['name'],
                    'is_base' => $language['is_base'],
                ]);

                continue;
            }

            Language::create($language);
        }
    }
}
