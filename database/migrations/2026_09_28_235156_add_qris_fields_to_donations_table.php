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
        Schema::table('donations', function (Blueprint $table) {
            $table->text('qr_string')->nullable();
            $table->unsignedInteger('total_payment')->nullable();
            $table->timestamp('qris_expires_at')->nullable();
            $table->string('dm_message_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('donations', function (Blueprint $table) {
            $table->dropColumn(['qr_string', 'total_payment', 'qris_expires_at', 'dm_message_id']);
        });
    }
};
