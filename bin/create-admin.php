<?php

declare(strict_types=1);

// Usage: php bin/create-admin.php admin@example.com 'a-long-password'
require __DIR__ . '/../vendor/autoload.php';

[$script, $email, $password] = array_pad($argv, 3, '');
if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || strlen($password) < 12) {
    fwrite(STDERR, "Usage: php bin/create-admin.php <email> <password of 12+ characters>\n");
    exit(1);
}

Dotenv\Dotenv::createImmutable(dirname(__DIR__))->safeLoad();
$container = (require __DIR__ . '/../config/container.php')(dirname(__DIR__));
$users = $container->get(App\Repository\UserRepository::class);
if ($users->findByEmail($email) !== null) {
    fwrite(STDERR, "A user with that email already exists.\n");
    exit(1);
}
$users->create($email, password_hash($password, PASSWORD_DEFAULT));
echo "Admin created.\n";
