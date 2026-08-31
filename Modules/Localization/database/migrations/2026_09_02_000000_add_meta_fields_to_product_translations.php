<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_translations', function (Blueprint $table): void {
            $table->string('meta_title')->nullable()->after('name');
            $table->text('meta_description')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('product_translations', function (Blueprint $table): void {
            $table->dropColumn(['meta_title', 'meta_description']);
        });
    }
};
