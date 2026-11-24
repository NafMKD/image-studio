<?php

return [
    'driver' => env('PIXELFORGE_DRIVER', 'fake'),
    'base_url' => env('PIXELFORGE_BASE_URL', 'https://pixelforge.promptedit.dev'),
    'api_key' => env('PIXELFORGE_API_KEY'),
    'webhook_secret' => env('PIXELFORGE_WEBHOOK_SECRET'),

    'contract_verified' => env('PIXELFORGE_CONTRACT_VERIFIED', false),
    'signature_header' => env('PIXELFORGE_SIGNATURE_HEADER', 'X-Pixelforge-Signature'),

    'rates' => [
        'prompt_tokens' => env('PIXELFORGE_PROMPT_USD_PER_M', '0.50'),
        'reference_tokens' => env('PIXELFORGE_REFERENCE_USD_PER_M', '0.50'),
        'output_tokens' => env('PIXELFORGE_OUTPUT_USD_PER_M', '2.00'),
    ],

    'reference_tokens_estimate' => (int) env('PIXELFORGE_REFERENCE_TOKENS_ESTIMATE', 250),
    'reference_tokens_max' => (int) env('PIXELFORGE_REFERENCE_TOKENS_MAX', 2000),
    'output_tokens_estimate' => [
        'standard' => ['square' => 500, 'landscape' => 750, 'portrait' => 750],
        'high' => ['square' => 1000, 'landscape' => 1500, 'portrait' => 1500],
    ],
    'output_tokens_max' => [
        'standard' => ['square' => 1000, 'landscape' => 1500, 'portrait' => 1500],
        'high' => ['square' => 2000, 'landscape' => 3000, 'portrait' => 3000],
    ],
];
