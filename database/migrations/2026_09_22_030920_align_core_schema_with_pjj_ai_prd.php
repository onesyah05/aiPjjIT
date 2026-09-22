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
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
            $table->timestamp('email_verified_at')->nullable()->after('email');
            $table->string('status')->default('active')->after('role');
            $table->timestamp('last_login_at')->nullable();
            $table->timestamp('last_membership_checked_at')->nullable();
        });

        Schema::table('discord_accounts', function (Blueprint $table) {
            $table->renameColumn('discord_id', 'discord_user_id');
            $table->renameColumn('access_token', 'access_token_encrypted');
            $table->renameColumn('refresh_token', 'refresh_token_encrypted');
            $table->string('global_name')->nullable();
            $table->string('avatar_hash')->nullable();
            $table->json('roles_json')->nullable();
        });

        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('semester')->nullable();
            $table->string('status')->default('active')->index();
            $table->timestamps();
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->foreignId('course_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            $table->string('mode')->default('general')->after('course_id');
            $table->text('summary')->nullable()->after('title');
            $table->string('status')->default('active')->after('summary');
            $table->timestamp('last_message_at')->nullable()->index();
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->string('status')->default('completed')->after('content');
            $table->string('provider')->nullable();
            $table->string('model')->nullable();
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->string('error_code')->nullable();
            $table->json('metadata_json')->nullable();
        });

        Schema::rename('knowledge', 'knowledges');

        Schema::table('knowledges', function (Blueprint $table) {
            $table->renameColumn('author_id', 'user_id');
            $table->foreignId('course_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            $table->text('description')->nullable()->after('title');
            $table->string('visibility')->default('private')->after('description')->index();
            $table->softDeletes();
        });

        Schema::table('knowledge_versions', function (Blueprint $table) {
            $table->renameColumn('version_number', 'version');
            $table->renameColumn('content_markdown', 'content');
            $table->string('source_type')->default('manual');
            $table->string('original_filename')->nullable();
            $table->string('status')->default('draft')->index();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('review_note')->nullable();
        });

        Schema::table('knowledge_reviews', function (Blueprint $table) {
            $table->renameColumn('status', 'action');
            $table->renameColumn('comments', 'note');
        });

        Schema::table('knowledges', function (Blueprint $table) {
            $table->foreignId('active_version_id')->nullable()->constrained('knowledge_versions')->nullOnDelete();
        });

        Schema::create('knowledge_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('knowledge_version_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('chunk_index');
            $table->string('heading_path')->nullable();
            $table->text('content');
            $table->unsignedInteger('token_count')->default(0);
            $table->uuid('vector_external_id')->nullable()->unique();
            $table->timestamps();
            $table->unique(['knowledge_version_id', 'chunk_index']);
        });

        Schema::table('message_sources', function (Blueprint $table) {
            $table->foreignId('knowledge_chunk_id')->nullable()->after('message_id')->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('rank')->default(1);
        });

        Schema::table('ai_credentials', function (Blueprint $table) {
            $table->renameColumn('name', 'label');
            $table->renameColumn('api_key', 'encrypted_secret');
            $table->string('provider')->default('gemini')->after('user_id');
            $table->string('credential_type')->default('api_key')->after('provider');
            $table->string('fingerprint', 64)->nullable()->unique();
            $table->string('masked_preview')->nullable();
            $table->string('status')->default('active')->index();
            $table->boolean('community_enabled')->default(true);
            $table->unsignedInteger('daily_request_limit')->nullable();
            $table->timestamp('cooldown_until')->nullable()->index();
            $table->unsignedInteger('failure_count')->default(0);
            $table->unsignedInteger('success_count')->default(0);
            $table->timestamp('last_used_at')->nullable();
            $table->string('last_error_code')->nullable();
            $table->timestamp('validated_at')->nullable();
            $table->softDeletes();
        });

        Schema::create('ai_credential_usage_daily', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credential_id')->constrained('ai_credentials')->cascadeOnDelete();
            $table->date('date');
            $table->unsignedInteger('request_count')->default(0);
            $table->unsignedInteger('success_count')->default(0);
            $table->unsignedInteger('failure_count')->default(0);
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->timestamps();
            $table->unique(['credential_id', 'date']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action')->index();
            $table->string('resource_type');
            $table->unsignedBigInteger('resource_id')->nullable();
            $table->json('metadata_json')->nullable();
            $table->string('ip_hash', 64)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['resource_type', 'resource_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('ai_credential_usage_daily');

        Schema::table('message_sources', function (Blueprint $table) {
            $table->dropConstrainedForeignId('knowledge_chunk_id');
            $table->dropColumn('rank');
        });

        Schema::dropIfExists('knowledge_chunks');

        Schema::table('knowledges', function (Blueprint $table) {
            $table->dropConstrainedForeignId('active_version_id');
        });

        Schema::table('knowledge_reviews', function (Blueprint $table) {
            $table->renameColumn('action', 'status');
            $table->renameColumn('note', 'comments');
        });

        Schema::table('knowledge_versions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn([
                'source_type',
                'original_filename',
                'status',
                'submitted_at',
                'reviewed_at',
                'review_note',
            ]);
            $table->renameColumn('version', 'version_number');
            $table->renameColumn('content', 'content_markdown');
        });

        Schema::table('knowledges', function (Blueprint $table) {
            $table->dropConstrainedForeignId('course_id');
            $table->dropColumn(['description', 'visibility', 'deleted_at']);
            $table->renameColumn('user_id', 'author_id');
        });

        Schema::rename('knowledges', 'knowledge');

        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn([
                'status',
                'provider',
                'model',
                'input_tokens',
                'output_tokens',
                'latency_ms',
                'error_code',
                'metadata_json',
            ]);
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('course_id');
            $table->dropColumn(['mode', 'summary', 'status', 'last_message_at']);
        });

        Schema::dropIfExists('courses');

        Schema::table('ai_credentials', function (Blueprint $table) {
            $table->dropColumn([
                'provider',
                'credential_type',
                'fingerprint',
                'masked_preview',
                'status',
                'community_enabled',
                'daily_request_limit',
                'cooldown_until',
                'failure_count',
                'success_count',
                'last_used_at',
                'last_error_code',
                'validated_at',
                'deleted_at',
            ]);
            $table->renameColumn('label', 'name');
            $table->renameColumn('encrypted_secret', 'api_key');
        });

        Schema::table('discord_accounts', function (Blueprint $table) {
            $table->dropColumn(['global_name', 'avatar_hash', 'roles_json']);
            $table->renameColumn('discord_user_id', 'discord_id');
            $table->renameColumn('access_token_encrypted', 'access_token');
            $table->renameColumn('refresh_token_encrypted', 'refresh_token');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['email_verified_at', 'status', 'last_login_at', 'last_membership_checked_at']);
            $table->string('email')->nullable(false)->change();
        });
    }
};
