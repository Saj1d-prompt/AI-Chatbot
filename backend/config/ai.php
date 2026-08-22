<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default AI Provider
    |--------------------------------------------------------------------------
    */

    'default' => env('AI_PROVIDER', 'cloudflare'),

    /*
    |--------------------------------------------------------------------------
    | System Prompt
    |--------------------------------------------------------------------------
    */

    'system_prompt' => env(
        'AI_SYSTEM_PROMPT',
        'You are a helpful AI assistant.'
    ),

    /*
    |--------------------------------------------------------------------------
    | Maximum Output Tokens
    |--------------------------------------------------------------------------
    */

    'max_tokens' => (int) env(
        'AI_MAX_TOKENS',
        1024
    ),

    /*
    |--------------------------------------------------------------------------
    | Conversation Context
    |--------------------------------------------------------------------------
    |
    | This controls how many of the most recent stored messages are sent to
    | the AI provider when generating a new response.
    |
    | IMPORTANT:
    | This does NOT control how many messages are stored in MySQL.
    | The database continues storing the full conversation.
    |
    */

    'context_message_limit' => (int) env(
        'AI_CONTEXT_MESSAGE_LIMIT',
        12
    ),

    /*
    |--------------------------------------------------------------------------
    | AI Providers
    |--------------------------------------------------------------------------
    */

    'providers' => [

        'cloudflare' => [
            'account_id' => env(
                'CLOUDFLARE_ACCOUNT_ID'
            ),

            'token' => env(
                'CLOUDFLARE_AI_TOKEN'
            ),

            'model' => env(
                'CLOUDFLARE_AI_MODEL',
                '@cf/meta/llama-3.1-8b-instruct-fast'
            ),
        ],

    ],

];