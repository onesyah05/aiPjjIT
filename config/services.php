<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'discord' => [
        'client_id' => env('DISCORD_CLIENT_ID'),
        'client_secret' => env('DISCORD_CLIENT_SECRET'),
        'bot_token' => env('DISCORD_BOT_TOKEN'),
        'bot_user_id' => env('DISCORD_BOT_USER_ID', env('DISCORD_CLIENT_ID')),
        'redirect' => env('DISCORD_REDIRECT_URI', '/auth/discord/callback'),
        'guild_id' => env('DISCORD_GUILD_ID'),
        'membership_recheck_hours' => (int) env('DISCORD_MEMBERSHIP_RECHECK_HOURS', 24),
        'admin_role_ids' => array_values(array_filter(explode(',', (string) env('DISCORD_ADMIN_ROLE_IDS', '')))),
        'reviewer_role_ids' => array_values(array_filter(explode(',', (string) env('DISCORD_REVIEWER_ROLE_IDS', '')))),
        'channel_ids' => array_values(array_filter(explode(',', (string) env('DISCORD_CHANNEL_IDS', '')))),
        'webhook_secret' => env('DISCORD_WEBHOOK_SECRET'),
        'bot_channel_id' => env('DISCORD_BOT_CHANNEL_ID', '1545331030083506226'),
        'public_key' => env('DISCORD_PUBLIC_KEY'),
        'donation_channel_id' => env('DISCORD_DONATION_CHANNEL_ID', '1554268154501271552'),
    ],

    'embedding' => [
        'provider' => env('EMBEDDING_PROVIDER', 'gemini'),
    ],

    'voyage' => [
        'base_url' => env('VOYAGE_BASE_URL', 'https://api.voyageai.com/v1'),
        'model' => env('VOYAGE_EMBEDDING_MODEL', 'voyage-3.5'),
        'batch_size' => (int) env('VOYAGE_BATCH_SIZE', 32),
        'rpm_delay' => (int) env('VOYAGE_RPM_DELAY', 21),
        'timeout' => (int) env('VOYAGE_TIMEOUT', 60),
    ],

    'pakasir' => [
        'slug' => env('PAKASIR_SLUG'),
        'api_key' => env('PAKASIR_API_KEY'),
        'webhook_secret' => env('PAKASIR_WEBHOOK_SECRET'),
        'sandbox' => (bool) env('PAKASIR_SANDBOX', true),
    ],

    'gemini' => [
        'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
        'model' => env('AI_DEFAULT_MODEL', 'gemini-3.8-flash'),
        'fallback_models' => array_values(array_filter(explode(',', (string) env('AI_FALLBACK_MODELS', 'gemini-flash-latest,gemini-flash-lite-latest')))),
        'embedding_model' => env('AI_EMBEDDING_MODEL', 'gemini-embedding-001'),
        'embedding_dimensions' => (int) env('AI_EMBEDDING_DIMENSIONS', 768),
        'timeout' => (int) env('AI_REQUEST_TIMEOUT', 60),
        'max_attempts' => (int) env('AI_MAX_CREDENTIAL_ATTEMPTS', 6),
    ],

    'qdrant' => [
        'enabled' => (bool) env('QDRANT_ENABLED', false),
        'url' => env('QDRANT_URL', 'http://localhost:6333'),
        'api_key' => env('QDRANT_API_KEY'),
        'collection' => env('QDRANT_COLLECTION', 'knowledge'),
        'timeout' => (int) env('QDRANT_TIMEOUT', 10),
        'score_threshold' => (float) env('QDRANT_SCORE_THRESHOLD', 0.28),
        'retrieval_limit' => (int) env('RETRIEVAL_LIMIT', 15),
    ],
];
