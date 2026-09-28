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
        Schema::create('donations', function (Blueprint $table) {
            $table->id();
            $table->string('order_id')->unique();
            $table->string('discord_interaction_id')->unique();
            $table->string('discord_user_id');
            $table->string('discord_username');
            $table->string('guild_id');
            $table->string('channel_id');
            $table->unsignedInteger('amount');
            $table->text('message')->nullable();
            $table->string('image_url')->nullable();
            $table->string('pakasir_txn_id')->nullable()->unique();
            $table->text('payment_url')->nullable();
            $table->boolean('is_sandbox');
            $table->string('status')->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'is_sandbox', 'discord_user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('donations');
    }
};
