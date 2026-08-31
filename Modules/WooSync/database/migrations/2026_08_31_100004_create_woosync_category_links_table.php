<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('woosync_category_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('taxonomy_term_id')->constrained('taxonomy_terms')->cascadeOnDelete();
            $table->unsignedBigInteger('woocommerce_category_id')->index();
            $table->timestamps();

            $table->unique('taxonomy_term_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('woosync_category_links');
    }
};
