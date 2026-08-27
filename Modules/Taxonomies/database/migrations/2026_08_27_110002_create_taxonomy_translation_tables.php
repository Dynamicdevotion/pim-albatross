<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Move taxonomies.name and taxonomy_terms.name into per-language translation
 * tables, mirroring product_translations. Data-preserving: every existing name
 * is copied as the base-language ("it") translation before the old columns are
 * dropped. `slug` stays on the parent tables (it is not translated).
 */
return new class extends Migration
{
    private function baseLanguageId(): int
    {
        $id = DB::table('languages')->where('code', 'it')->value('id');

        if ($id === null) {
            throw new RuntimeException('Base language "it" is missing from the languages table.');
        }

        return (int) $id;
    }

    public function up(): void
    {
        Schema::create('taxonomy_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('taxonomy_id')->constrained()->cascadeOnDelete();
            $table->foreignId('language_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
            $table->unique(['taxonomy_id', 'language_id']);
        });

        Schema::create('taxonomy_term_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('taxonomy_term_id')->constrained()->cascadeOnDelete();
            $table->foreignId('language_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
            $table->unique(['taxonomy_term_id', 'language_id']);
        });

        $baseId = $this->baseLanguageId();
        $now = now();

        $taxonomies = DB::table('taxonomies')->get();
        foreach ($taxonomies as $row) {
            DB::table('taxonomy_translations')->insert([
                'taxonomy_id' => $row->id,
                'language_id' => $baseId,
                'name' => $row->name,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
        if (DB::table('taxonomy_translations')->count() !== $taxonomies->count()) {
            throw new RuntimeException('taxonomy_translations row count does not match taxonomies.');
        }

        $terms = DB::table('taxonomy_terms')->get();
        foreach ($terms as $row) {
            DB::table('taxonomy_term_translations')->insert([
                'taxonomy_term_id' => $row->id,
                'language_id' => $baseId,
                'name' => $row->name,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
        if (DB::table('taxonomy_term_translations')->count() !== $terms->count()) {
            throw new RuntimeException('taxonomy_term_translations row count does not match taxonomy_terms.');
        }

        Schema::table('taxonomies', fn (Blueprint $table) => $table->dropColumn('name'));
        Schema::table('taxonomy_terms', fn (Blueprint $table) => $table->dropColumn('name'));
    }

    public function down(): void
    {
        Schema::table('taxonomies', fn (Blueprint $table) => $table->string('name')->nullable()->after('id'));
        Schema::table('taxonomy_terms', fn (Blueprint $table) => $table->string('name')->nullable()->after('parent_id'));

        $baseId = $this->baseLanguageId();

        foreach (DB::table('taxonomy_translations')->where('language_id', $baseId)->get() as $row) {
            DB::table('taxonomies')->where('id', $row->taxonomy_id)->update(['name' => $row->name]);
        }
        foreach (DB::table('taxonomy_term_translations')->where('language_id', $baseId)->get() as $row) {
            DB::table('taxonomy_terms')->where('id', $row->taxonomy_term_id)->update(['name' => $row->name]);
        }

        DB::table('taxonomies')->whereNull('name')->update(['name' => 'untitled']);
        DB::table('taxonomy_terms')->whereNull('name')->update(['name' => 'untitled']);

        Schema::table('taxonomies', fn (Blueprint $table) => $table->string('name')->nullable(false)->change());
        Schema::table('taxonomy_terms', fn (Blueprint $table) => $table->string('name')->nullable(false)->change());

        Schema::dropIfExists('taxonomy_term_translations');
        Schema::dropIfExists('taxonomy_translations');
    }
};
