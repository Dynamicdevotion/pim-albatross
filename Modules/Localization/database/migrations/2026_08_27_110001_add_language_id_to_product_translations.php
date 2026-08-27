<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Move product_translations off the `locale` string onto a `language_id` FK
 * into the new `languages` table. Data-preserving: existing rows are matched
 * by locale code.
 *
 * Order matters on MySQL: the new unique(product_id, language_id) must exist
 * before the old unique(product_id, locale) is dropped, otherwise the
 * product_id foreign key is left without a covering index.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('product_translations', 'language_id')) {
            Schema::table('product_translations', function (Blueprint $table) {
                $table->foreignId('language_id')->nullable()->after('product_id');
            });
        }

        $idByCode = DB::table('languages')->pluck('id', 'code');

        foreach (DB::table('product_translations')->whereNull('language_id')->get() as $row) {
            $languageId = $idByCode[$row->locale] ?? null;

            if ($languageId === null) {
                throw new RuntimeException(
                    "product_translations #{$row->id}: locale '{$row->locale}' has no matching language.",
                );
            }

            DB::table('product_translations')->where('id', $row->id)
                ->update(['language_id' => $languageId]);
        }

        Schema::table('product_translations', function (Blueprint $table) {
            $table->unsignedBigInteger('language_id')->nullable(false)->change();
            $table->foreign('language_id')->references('id')->on('languages')->cascadeOnDelete();
            $table->unique(['product_id', 'language_id']);
        });

        if (Schema::hasColumn('product_translations', 'locale')) {
            Schema::table('product_translations', function (Blueprint $table) {
                $table->dropUnique('product_translations_product_id_locale_unique');
                $table->dropColumn('locale');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('product_translations', 'locale')) {
            Schema::table('product_translations', function (Blueprint $table) {
                $table->string('locale', 5)->nullable()->after('product_id');
            });

            $codeById = DB::table('languages')->pluck('code', 'id');

            foreach (DB::table('product_translations')->get() as $row) {
                DB::table('product_translations')->where('id', $row->id)
                    ->update(['locale' => $codeById[$row->language_id] ?? 'it']);
            }

            Schema::table('product_translations', function (Blueprint $table) {
                $table->string('locale', 5)->nullable(false)->change();
                $table->unique(['product_id', 'locale']);
            });
        }

        Schema::table('product_translations', function (Blueprint $table) {
            $table->dropForeign(['language_id']);
            $table->dropUnique('product_translations_product_id_language_id_unique');
            $table->dropColumn('language_id');
        });
    }
};
