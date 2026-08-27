<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Variable-product support on a single self-referencing products table.
     *
     *  - `type`: 'simple' (default, behaviour unchanged) | 'variable' (a
     *    container that only groups variants — no own price/stock) | 'variant'
     *    (a child product with its own sku, price, stock and terms).
     *  - `parent_id`: a variant points at its 'variable' parent. Deleting the
     *    parent deletes its variants, which in turn cascade to their prices,
     *    translations and taxonomy pivots.
     *  - `stock`: on-hand count; null for 'variable' rows.
     *
     * Existing rows become 'simple' with stock 0 and no parent via the defaults.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->string('type')->default('simple')->after('id');
            $table->foreignId('parent_id')->nullable()->after('type')
                ->constrained('products')->cascadeOnDelete();
            $table->unsignedInteger('stock')->nullable()->default(0)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropForeign(['parent_id']);
            $table->dropColumn(['type', 'parent_id', 'stock']);
        });
    }
};
