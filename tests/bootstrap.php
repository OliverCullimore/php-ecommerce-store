<?php

declare(strict_types=1);

if (is_file(__DIR__ . '/../vendor/autoload.php')) {
    require __DIR__ . '/../vendor/autoload.php';

    return;
}

// Fallback for environments without Composer: PSR-4 autoload + TestCase shim.
spl_autoload_register(static function (string $class): void {
    foreach (['App\\' => __DIR__ . '/../src/', 'Tests\\' => __DIR__ . '/'] as $prefix => $dir) {
        if (str_starts_with($class, $prefix)) {
            $file = $dir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            if (is_file($file)) {
                require $file;
            }
        }
    }
});
require __DIR__ . '/shim/TestCase.php';
