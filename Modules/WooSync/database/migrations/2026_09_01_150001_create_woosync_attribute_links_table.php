<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('woosync_attribute_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('taxonomy_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('woocommerce_attribute_id')->index();
            $table->timestamps();

            $table->unique('taxonomy_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('woosync_attribute_links');
    }
};
