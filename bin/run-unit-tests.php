<?php

declare(strict_types=1);

// Dependency-free runner for tests/<suite> (default Unit; pass Functional etc. as argv[1]) (uses the shim in tests/shim when PHPUnit is absent).
// Prefer `composer test:unit` when vendor/ exists.
require __DIR__ . '/../tests/bootstrap.php';

$suite = $argv[1] ?? 'Unit';
$pass = 0;
$fail = 0;
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(realpath(__DIR__ . "/../tests/$suite")));
foreach ($files as $file) {
    if (!str_ends_with((string) $file, 'Test.php')) {
        continue;
    }
    $rel = substr((string) $file, strlen(realpath(__DIR__ . '/../tests') . '/'), -4);
    $class = 'Tests\\' . str_replace('/', '\\', $rel);
    foreach (get_class_methods($class) as $method) {
        if (!str_starts_with($method, 'test')) {
            continue;
        }
        $t = new $class();
        try {
            $t->$method();
            if ($t->expectedException() !== null) {
                throw new RuntimeException("Expected {$t->expectedException()} was not thrown");
            }
            $pass++;
        } catch (Throwable $e) {
            if ($t->expectedException() !== null && $e instanceof ($t->expectedException())) {
                $pass++;
                continue;
            }
            $fail++;
            echo "FAIL {$class}::{$method}\n  " . get_class($e) . ': ' . $e->getMessage() . "\n";
        }
    }
}
echo "\n$pass passed, $fail failed\n";
exit($fail > 0 ? 1 : 0);
