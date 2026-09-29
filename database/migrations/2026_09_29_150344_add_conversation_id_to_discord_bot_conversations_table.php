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
        Schema::table('discord_bot_conversations', function (Blueprint $table) {
            $table->foreignId('conversation_id')
                ->nullable()
                ->after('discord_message_id')
                ->constrained('conversations')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('discord_bot_conversations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('conversation_id');
        });
    }
};
