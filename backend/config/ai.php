<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default AI Provider
    |--------------------------------------------------------------------------
    |
    | Determines which AI provider the application should use.
    |
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
    |
    | Controls the maximum number of tokens that the AI provider may generate
    | for a single assistant response.
    |
    */

    'max_tokens' => (int) env('AI_MAX_TOKENS', 1024),

    /*
    |--------------------------------------------------------------------------
    | AI Providers
    |--------------------------------------------------------------------------
    */

    'providers' => [

        'cloudflare' => [
            'account_id' => env('CLOUDFLARE_ACCOUNT_ID'),

            'token' => env('CLOUDFLARE_AI_TOKEN'),

            'model' => env(
                'CLOUDFLARE_AI_MODEL',
                '@cf/meta/llama-3.1-8b-instruct-fast'
            ),
        ],

    ],

];