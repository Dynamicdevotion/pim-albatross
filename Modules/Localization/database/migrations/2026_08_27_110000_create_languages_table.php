<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Localization\Support\LanguageCatalog;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('languages', function (Blueprint $table) {
            $table->id();
            $table->string('code', 5)->unique();
            $table->string('name');
            $table->boolean('active')->default(false);
            $table->boolean('is_base')->default(false);
            $table->timestamps();
        });

        // Seed the catalogue immediately: later data migrations resolve
        // language_id from these rows.
        $now = now();

        DB::table('languages')->insert(array_map(
            fn (array $language): array => $language + ['created_at' => $now, 'updated_at' => $now],
            LanguageCatalog::all(),
        ));
    }

    public function down(): void
    {
        Schema::dropIfExists('languages');
    }
};
