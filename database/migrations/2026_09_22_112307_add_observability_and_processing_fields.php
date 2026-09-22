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
        Schema::table('knowledge_versions', function (Blueprint $table) {
            $table->string('processing_status')->default('pending')->after('is_embedded')->index();
            $table->text('processing_error')->nullable()->after('processing_status');
            $table->timestamp('embedded_at')->nullable()->after('processing_error');
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->uuid('client_request_id')->nullable()->after('conversation_id');
            $table->unique(['conversation_id', 'client_request_id']);
        });

        Schema::create('ai_request_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('conversation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('message_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('credential_id')->nullable()->constrained('ai_credentials')->nullOnDelete();
            $table->string('operation')->default('chat');
            $table->string('provider')->default('gemini');
            $table->string('model')->nullable();
            $table->string('status')->index();
            $table->string('error_code')->nullable()->index();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->unsignedSmallInteger('retrieved_chunks')->default(0);
            $table->timestamps();
            $table->index(['created_at', 'status']);
        });

        Schema::create('user_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('message_id')->constrained()->cascadeOnDelete();
            $table->string('rating');
            $table->string('reason')->nullable();
            $table->text('comment')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'message_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_feedback');
        Schema::dropIfExists('ai_request_logs');

        Schema::table('messages', function (Blueprint $table) {
            $table->dropUnique(['conversation_id', 'client_request_id']);
            $table->dropColumn('client_request_id');
        });

        Schema::table('knowledge_versions', function (Blueprint $table) {
            $table->dropColumn(['processing_status', 'processing_error', 'embedded_at']);
        });
    }
};
