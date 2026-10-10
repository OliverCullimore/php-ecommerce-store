<?php

declare(strict_types=1);

namespace App\Support;

final class Csrf
{
    public static function token(): string
    {
        return $_SESSION['_csrf'] ??= bin2hex(random_bytes(32));
    }

    public static function valid(?string $submitted): bool
    {
        return $submitted !== null && $submitted !== '' && hash_equals(self::token(), $submitted);
    }
}
