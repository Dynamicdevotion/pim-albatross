<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('import_records', function (Blueprint $table): void {
            $table->boolean('create_missing_terms')->default(false)->after('update_existing');
            $table->boolean('replace_taxonomy_terms')->default(false)->after('create_missing_terms');
        });
    }

    public function down(): void
    {
        Schema::table('import_records', function (Blueprint $table): void {
            $table->dropColumn(['create_missing_terms', 'replace_taxonomy_terms']);
        });
    }
};
