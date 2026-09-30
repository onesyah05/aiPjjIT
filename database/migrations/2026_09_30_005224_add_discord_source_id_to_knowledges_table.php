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
        Schema::table('knowledges', function (Blueprint $table) {
            $table->string('discord_source_id')->nullable()->after('title')->index();
        });

        // Discord sync titles look like "[Discord {snowflake}] {label}".
        DB::table('knowledges')->where('title', 'like', '[Discord%')->orderBy('id')->chunkById(500, function ($knowledges): void {
            foreach ($knowledges as $knowledge) {
                if (preg_match('/^\[Discord (\d+)\]/', (string) $knowledge->title, $matches)) {
                    DB::table('knowledges')
                        ->where('id', $knowledge->id)
                        ->update(['discord_source_id' => $matches[1]]);
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('knowledges', function (Blueprint $table) {
            $table->dropColumn('discord_source_id');
        });
    }
};
