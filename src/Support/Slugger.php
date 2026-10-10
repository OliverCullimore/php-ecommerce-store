<?php

declare(strict_types=1);

namespace App\Support;

final class Slugger
{
    public static function slug(string $text): string
    {
        $s = strtolower(trim((string) preg_replace('/[^A-Za-z0-9]+/', '-', $text), '-'));

        return $s === '' ? 'n-a' : $s;
    }
}
