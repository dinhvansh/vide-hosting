<?php

return [
    'deployment_provider' => env('DEPLOYMENT_PROVIDER', 'fake'),
    'platform_domain' => env('PLATFORM_DOMAIN', 'apps.vive.local'),
    'frontend_url' => env('FRONTEND_URL', 'http://localhost:3000'),
    'api_token_ttl_minutes' => env('API_TOKEN_TTL_MINUTES', 43200),
    'mcp_token_ttl_minutes' => env('MCP_TOKEN_TTL_MINUTES', 525600),
    'custom_domains_enabled' => env('CUSTOM_DOMAINS_ENABLED', false),
    'admin_seed' => [
        'name' => env('ADMIN_NAME', 'Vive Admin'),
        'email' => env('ADMIN_EMAIL', 'admin@vive.local'),
        'password' => env('ADMIN_PASSWORD', 'ChangeMe123!'),
    ],
    'deployments' => [
        'stale_minutes' => (int) env('DEPLOYMENT_STALE_MINUTES', 35),
        'queued_minutes' => (int) env('DEPLOYMENT_QUEUED_MINUTES', 10),
    ],
    'dokploy' => [
        'url' => env('DOKPLOY_URL'),
        'token' => env('DOKPLOY_TOKEN'),
        'connect_timeout' => (int) env('DOKPLOY_CONNECT_TIMEOUT', 5),
        'timeout' => (int) env('DOKPLOY_TIMEOUT', 30),
        'deployment_timeout' => (int) env('DOKPLOY_DEPLOYMENT_TIMEOUT', 1500),
        'poll_interval' => (int) env('DOKPLOY_POLL_INTERVAL', 2),
        'railpack_version' => env('DOKPLOY_RAILPACK_VERSION', '0.15.4'),
    ],

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

];
