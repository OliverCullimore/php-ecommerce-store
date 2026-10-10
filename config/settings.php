<?php

declare(strict_types=1);

$env = static fn (string $key, string $default = ''): string => (string) ($_ENV[$key] ?? getenv($key) ?: $default);

return [
    'app' => [
        'env' => $env('APP_ENV', 'prod'),
        'debug' => $env('APP_DEBUG', '0') === '1',
        'url' => $env('APP_URL', 'http://localhost:8080'),
    ],
    'db' => [
        'host' => $env('DB_HOST', 'db'),
        'port' => (int) $env('DB_PORT', '3306'),
        'name' => $env('DB_NAME', 'db'),
        'user' => $env('DB_USER', 'db'),
        'pass' => $env('DB_PASS', 'db'),
    ],
    'payment' => [
        'gateway' => $env('PAYMENT_GATEWAY', 'fake'),
        'gp' => [
            'merchant_id' => $env('GP_MERCHANT_ID'),
            'account' => $env('GP_ACCOUNT', 'internet'),
            'secret' => $env('GP_SHARED_SECRET'),
            'hpp_url' => $env('GP_HPP_URL', 'https://pay.sandbox.realexpayments.com/pay'),
            'api_url' => $env('GP_API_URL', 'https://api.sandbox.realexpayments.com/epage-remote.cgi'),
            'refund_password' => $env('GP_REFUND_PASSWORD'),
        ],
    ],
    'shop' => [
        'name' => $env('SHOP_NAME', 'Filament & Print Shop'),
        'address' => $env('SHOP_ADDRESS', ''),
    ],
];
