<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('woosync_product_links', function (Blueprint $table): void {
            // The reconciled stock value last written to both sides. Null until
            // the first stock sync; from then on it is the baseline for the
            // delta model in WooSyncRunner: current PIM stock minus this value
            // is the PIM-side change to add on top of the store's current,
            // sales-adjusted quantity. Signed — a store with backorders can
            // report a negative quantity.
            $table->integer('last_known_stock')->nullable()->after('images_hash');
        });
    }

    public function down(): void
    {
        Schema::table('woosync_product_links', function (Blueprint $table): void {
            $table->dropColumn('last_known_stock');
        });
    }
};
