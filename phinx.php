<?php

declare(strict_types=1);

$env = static fn (string $k, string $d = ''): string => (string) ($_ENV[$k] ?? getenv($k) ?: $d);

if (is_file(__DIR__ . '/.env') && class_exists(Dotenv\Dotenv::class)) {
    Dotenv\Dotenv::createImmutable(__DIR__)->safeLoad();
}

$conn = static fn (string $name): array => [
    'adapter' => 'mysql',
    'host' => $env('DB_HOST', 'db'),
    'name' => $name,
    'user' => $env('DB_USER', 'db'),
    'pass' => $env('DB_PASS', 'db'),
    'port' => (int) $env('DB_PORT', '3306'),
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
];

return [
    'paths' => ['migrations' => '%%PHINX_CONFIG_DIR%%/migrations'],
    'environments' => [
        'default_migration_table' => 'phinxlog',
        'default_environment' => 'development',
        'development' => $conn($env('DB_NAME', 'db')),
        'testing' => $conn($env('DB_TEST_NAME', 'db_test')),
    ],
    'version_order' => 'creation',
];
