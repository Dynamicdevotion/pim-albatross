<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('export_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('format'); // csv | xlsx
            $table->json('columns');
            $table->json('filters')->nullable();
            $table->json('sort')->nullable();
            $table->string('status')->default('pending')->index();
            $table->unsignedInteger('total_rows')->nullable();
            $table->unsignedInteger('row_count')->nullable();
            $table->string('stored_path')->nullable();
            $table->string('original_filename');
            $table->string('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('export_records');
    }
};
