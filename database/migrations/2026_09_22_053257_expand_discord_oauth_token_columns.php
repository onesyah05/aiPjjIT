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
        Schema::table('discord_accounts', function (Blueprint $table) {
            $table->text('access_token_encrypted')->change();
            $table->text('refresh_token_encrypted')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        /**
         * Encrypted OAuth tokens cannot be safely reduced to VARCHAR(255)
         * without truncating active credentials.
         */
    }
};
