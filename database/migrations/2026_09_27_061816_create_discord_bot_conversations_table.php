<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discord_bot_conversations', function (Blueprint $table): void {
            $table->id();
            $table->string('discord_channel_id')->index();
            $table->string('discord_user_id')->index();
            $table->string('discord_message_id')->unique();
            $table->string('discord_username');
            $table->text('question');
            $table->text('answer')->nullable();
            $table->enum('status', ['pending', 'completed', 'failed'])->default('pending');
            $table->string('error_code')->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discord_bot_conversations');
    }
};
