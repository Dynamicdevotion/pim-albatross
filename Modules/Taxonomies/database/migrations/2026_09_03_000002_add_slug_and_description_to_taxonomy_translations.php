<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A translated, user-facing slug + rich-text description per language.
 *
 * Distinct from `taxonomies.slug`, which stays untouched: that column is the
 * stable internal key WooSync and ImportGestionali already look up by
 * (`CategoryResolver`, `AttributeResolver`), so it keeps working unmodified.
 * The form now treats it as a read-only internal code instead of the
 * user-editable "slug".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('taxonomy_translations', function (Blueprint $table): void {
            $table->string('slug')->nullable()->after('name');
            $table->text('description')->nullable()->after('slug');
            $table->unique(['language_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::table('taxonomy_translations', function (Blueprint $table): void {
            $table->dropUnique('taxonomy_translations_language_id_slug_unique');
            $table->dropColumn(['slug', 'description']);
        });
    }
};
