<?php

declare(strict_types=1);

// Cron: */15 * * * * php /path/to/bin/sweep-abandoned.php
// Marks storefront orders still `draft` after 2 hours as `abandoned`. Manual orders are exempt.
require __DIR__ . '/../vendor/autoload.php';

$root = dirname(__DIR__);
Dotenv\Dotenv::createImmutable($root)->safeLoad();
$container = (require $root . '/config/container.php')($root);
$count = $container->get(App\Domain\Order\OrderService::class)->sweepAbandoned(new DateTimeImmutable());
echo "Abandoned {$count} draft order(s).\n";
