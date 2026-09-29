<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('knowledge_chunks', function (Blueprint $table) {
            $table->string('content_hash', 32)->nullable()->after('content')->index();
        });

        DB::table('knowledge_chunks')->select('id', 'content')->orderBy('id')->chunkById(500, function ($chunks): void {
            foreach ($chunks as $chunk) {
                DB::table('knowledge_chunks')
                    ->where('id', $chunk->id)
                    ->update(['content_hash' => md5((string) $chunk->content)]);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('knowledge_chunks', function (Blueprint $table) {
            $table->dropColumn('content_hash');
        });
    }
};
