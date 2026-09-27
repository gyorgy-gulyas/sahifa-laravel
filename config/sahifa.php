<?php

return [
    // Your API key. Create a free one at https://sahifa.dev/en/account
    'api_key' => env('SAHIFA_API_KEY'),

    'base_url' => env('SAHIFA_BASE_URL', 'https://api.sahifa.dev'),

    // Seconds to wait for one render.
    'timeout' => (int) env('SAHIFA_TIMEOUT', 120),

    // Retries on 429, 502, 503 and 504 responses, waiting for Retry-After.
    'retries' => (int) env('SAHIFA_RETRIES', 3),

    // Default options for every PDF, e.g. ['format' => 'A4', 'margin' => '15mm'].
    'pdf_defaults' => [
        'format' => 'A4',
    ],
];
