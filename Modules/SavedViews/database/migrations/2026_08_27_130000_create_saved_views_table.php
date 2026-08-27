<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saved_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('resource')->index();
            $table->string('name');
            $table->json('filters')->nullable();
            $table->json('columns')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'resource', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_views');
    }
};
