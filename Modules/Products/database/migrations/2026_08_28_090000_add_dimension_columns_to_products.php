<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Standard e-commerce shipping dimensions, on the same single
     * self-referencing products table — so a variant inherits the columns for
     * free, exactly like price and stock.
     *
     *  - `weight`: kg, `decimal(8,3)` (gram resolution).
     *  - `length` / `width` / `height`: cm, `decimal(8,2)`.
     *
     * All nullable and optional; only meaningful on `simple` and `variant`
     * rows. A `variable` container never carries its own dimensions — the
     * model's `saving` hook nulls them, the same backstop used for `stock`.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->decimal('weight', 8, 3)->nullable()->after('stock');
            $table->decimal('length', 8, 2)->nullable()->after('weight');
            $table->decimal('width', 8, 2)->nullable()->after('length');
            $table->decimal('height', 8, 2)->nullable()->after('width');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn(['weight', 'length', 'width', 'height']);
        });
    }
};
