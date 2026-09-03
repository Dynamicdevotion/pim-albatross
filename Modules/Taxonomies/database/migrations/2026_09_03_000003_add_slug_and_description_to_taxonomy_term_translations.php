<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A translated, user-facing slug + rich-text description per language.
 *
 * Distinct from `taxonomy_terms.slug`, which stays untouched (see the sibling
 * migration on `taxonomy_translations` for why).
 *
 * No compound unique index here: uniqueness for the new slug is scoped to
 * "within the same taxonomy, same language", but this table only knows
 * `taxonomy_term_id` — the taxonomy itself lives one join away, on
 * `taxonomy_terms`. A DB-level constraint would need `taxonomy_id`
 * duplicated onto every translation row just to index it. Given this is a
 * low-concurrency admin tool and two terms of the same taxonomy sharing a
 * slug is a cosmetic clash rather than a data-integrity problem (unlike two
 * products sharing a SKU), uniqueness is enforced at the application layer
 * only (see HandlesTranslatableName + SlugGenerator).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('taxonomy_term_translations', function (Blueprint $table): void {
            $table->string('slug')->nullable()->after('name');
            $table->text('description')->nullable()->after('slug');
        });
    }

    public function down(): void
    {
        Schema::table('taxonomy_term_translations', function (Blueprint $table): void {
            $table->dropColumn(['slug', 'description']);
        });
    }
};
