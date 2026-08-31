<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('woosync_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('store_url')->nullable();
            $table->text('consumer_key')->nullable();     // encrypted
            $table->text('consumer_secret')->nullable();  // encrypted
            $table->timestamp('last_tested_at')->nullable();
            $table->boolean('last_test_ok')->nullable();
            $table->string('last_test_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('woosync_settings');
    }
};
