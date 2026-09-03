<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_translations', function (Blueprint $table): void {
            $table->string('slug')->nullable()->after('name');
            $table->unique(['language_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::table('product_translations', function (Blueprint $table): void {
            $table->dropUnique('product_translations_language_id_slug_unique');
            $table->dropColumn('slug');
        });
    }
};
