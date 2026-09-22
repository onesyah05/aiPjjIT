<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('knowledge_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('knowledge_id')->constrained('knowledge')->onDelete('cascade');
            $table->integer('version_number');
            $table->text('content_markdown');
            $table->boolean('is_embedded')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('knowledge_versions');
    }
};
